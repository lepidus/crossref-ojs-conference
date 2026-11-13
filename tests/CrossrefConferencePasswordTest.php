<?php

use APP\core\Application;
use APP\plugins\generic\crossrefConference\classes\APIKeyEncryption;
use APP\plugins\generic\crossrefConference\CrossrefConferencePlugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\plugins\PluginSettingsDAO;
use PKP\tests\PKPTestCase;

class CrossrefConferencePasswordTest extends PKPTestCase
{
    private const SECRET = 'a-secret-for-the-unit-tests';

    private int $contextId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $contextDao = Application::getContextDAO();
        $this->contextId = (int) DB::table($contextDao->tableName)->value($contextDao->primaryKeyColumn);
        DB::table('plugin_settings')
            ->where('context_id', '=', $this->contextId)
            ->where('plugin_name', '=', 'crossrefconferenceplugin')
            ->delete();
        $this->forgetCachedPluginSettings();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        $this->forgetCachedPluginSettings();

        parent::tearDown();
    }

    public static function createPlugin(string $secret): CrossrefConferencePlugin
    {
        return new class ($secret) extends CrossrefConferencePlugin {
            public function __construct(private string $secret)
            {
                parent::__construct();
            }

            public function getApiKeyEncryption(): APIKeyEncryption
            {
                return new APIKeyEncryption($this->secret);
            }
        };
    }

    public function testPasswordIsStoredEncrypted(): void
    {
        $plugin = self::createPlugin(self::SECRET);

        $plugin->updateSetting($this->contextId, 'password', 'crossref-password');

        $storedPassword = $this->storedPassword();
        $this->assertStringStartsWith('base64:', $storedPassword);
        $this->assertSame('crossref-password', (new APIKeyEncryption(self::SECRET))->decryptString($storedPassword));
        $this->assertSame('crossref-password', $plugin->getDepositPassword($this->contextId));
    }

    public function testEmptyPasswordKeepsTheStoredOne(): void
    {
        $plugin = self::createPlugin(self::SECRET);
        $plugin->updateSetting($this->contextId, 'password', 'crossref-password');

        $plugin->updateSetting($this->contextId, 'password', '');

        $this->assertSame('crossref-password', $plugin->getDepositPassword($this->contextId));
    }

    public function testAlreadyEncryptedPasswordIsNotEncryptedAgain(): void
    {
        $encryptedPassword = (new APIKeyEncryption(self::SECRET))->encryptString('crossref-password');

        self::createPlugin(self::SECRET)->updateSetting($this->contextId, 'password', $encryptedPassword);

        $this->assertSame($encryptedPassword, $this->storedPassword());
    }

    public function testPasswordIsNotStoredWithoutASecret(): void
    {
        self::createPlugin('')->updateSetting($this->contextId, 'password', 'crossref-password');

        $this->assertNull($this->storedPassword());
    }

    public function testPasswordStoredInPlainTextIsStillUsedForDeposits(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao->updateSetting($this->contextId, 'crossrefconferenceplugin', 'password', 'crossref-password', 'string');

        $this->assertSame('crossref-password', self::createPlugin(self::SECRET)->getDepositPassword($this->contextId));
    }

    public function testOtherSettingsAreStoredAsTyped(): void
    {
        $plugin = self::createPlugin(self::SECRET);

        $plugin->updateSetting($this->contextId, 'username', 'lepidus');

        $this->assertSame('lepidus', $plugin->getSetting($this->contextId, 'username'));
    }

    public function testSettingsFormNeverSendsThePasswordBack(): void
    {
        $plugin = self::createPlugin(self::SECRET);
        $plugin->updateSetting($this->contextId, 'password', 'crossref-password');
        $this->mockRequest();
        $journal = Application::getContextDAO()->getById($this->contextId);

        $passwordField = current(array_filter($plugin->getSettingsObject()->getFields($journal), fn ($field) => $field->name === 'password'));

        $this->assertSame('', $passwordField->value);
    }

    public function testSettingsRefuseAPasswordThatCannotBeEncrypted(): void
    {
        $errors = self::createPlugin('')->getSettingsObject()->validate([
            'depositorName' => 'Lepidus Tecnologia',
            'depositorEmail' => 'doi@lepidus.com.br',
            'conferenceNameOption' => 'issueTitle',
            'password' => 'crossref-password',
        ]);

        $this->assertArrayHasKey('password', $errors);
    }

    private function storedPassword(): ?string
    {
        return DB::table('plugin_settings')
            ->where('context_id', '=', $this->contextId)
            ->where('plugin_name', '=', 'crossrefconferenceplugin')
            ->where('setting_name', '=', 'password')
            ->value('setting_value');
    }

    private function forgetCachedPluginSettings(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $cacheId = (new ReflectionMethod($pluginSettingsDao, '_getCacheId'))->invoke($pluginSettingsDao, $this->contextId, 'crossrefconferenceplugin', true);
        Cache::forget($cacheId);
    }
}
