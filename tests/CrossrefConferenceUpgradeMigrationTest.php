<?php

use APP\core\Application;
use APP\plugins\generic\crossrefConference\classes\APIKeyEncryption;
use APP\plugins\generic\crossrefConference\classes\migration\CrossrefConferenceUpgradeMigration;
use APP\plugins\generic\crossrefConference\CrossrefConferencePlugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\doi\Doi;
use PKP\plugins\PluginSettingsDAO;
use PKP\tests\PKPTestCase;

class CrossrefConferenceUpgradeMigrationTest extends PKPTestCase
{
    private CrossrefConferencePlugin $plugin;
    private int $contextId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->plugin = new class () extends CrossrefConferencePlugin {
            public function getApiKeyEncryption(): APIKeyEncryption
            {
                return new APIKeyEncryption('a-secret-for-the-unit-tests');
            }
        };
        $contextDao = Application::getContextDAO();
        $this->contextId = (int) DB::table($contextDao->tableName)->value($contextDao->primaryKeyColumn);

        DB::table($contextDao->settingsTableName)
            ->where($contextDao->primaryKeyColumn, '=', $this->contextId)
            ->whereIn('setting_name', [Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, Context::SETTING_DOI_AUTOMATIC_DEPOSIT])
            ->delete();
        $this->setContextSetting(Context::SETTING_ENABLE_DOIS, '1');
        $this->setContextSetting(Context::SETTING_ENABLED_DOI_TYPES, json_encode(['publication', 'issue']));
        DB::table('plugin_settings')
            ->where('context_id', '=', $this->contextId)
            ->whereIn('plugin_name', [$this->plugin->getName(), CrossrefConferenceUpgradeMigration::LEGACY_PLUGIN_NAME])
            ->delete();
        $this->forgetCachedPluginSettings();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        $this->forgetCachedPluginSettings();

        parent::tearDown();
    }

    private function forgetCachedPluginSettings(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $cacheId = (new ReflectionMethod($pluginSettingsDao, '_getCacheId'))->invoke($pluginSettingsDao, $this->contextId, $this->plugin->getName(), true);
        Cache::forget($cacheId);
    }

    public static function legacyStatuses(): array
    {
        return [
            'registered' => ['registered', null, Doi::STATUS_REGISTERED],
            'marked registered' => ['markedRegistered', null, Doi::STATUS_REGISTERED],
            'failed' => ['failed', null, Doi::STATUS_ERROR],
            'registered DOI without status' => [null, '10.5540/03.2020.007.01.0339', Doi::STATUS_REGISTERED],
            'never deposited' => [null, null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legacyStatuses')]
    public function testLegacyStatusBecomesDoiStatus(?string $legacyStatus, ?string $registeredDoi, ?int $expectedStatus): void
    {
        $this->assertSame($expectedStatus, CrossrefConferenceUpgradeMigration::doiStatusFromLegacy($legacyStatus, $registeredDoi));
    }

    public function testFailedDepositIsCarriedOverToTheDoi(): void
    {
        [$submissionId, $doiId] = $this->insertPaperWithDoi();
        $this->insertLegacySubmissionSettings($submissionId, [
            'crossrefConference::status' => 'failed',
            'crossrefConference::batchId' => 'proceeding_1606846180',
            'crossrefConference::failedMsg' => 'Invalid conference_date',
        ]);

        $this->runMigration();

        $this->assertSame(Doi::STATUS_ERROR, (int) DB::table('dois')->where('doi_id', '=', $doiId)->value('status'));
        $this->assertSame('proceeding_1606846180', $this->doiSetting($doiId, 'crossrefconferenceplugin_batchId'));
        $this->assertSame('Invalid conference_date', $this->doiSetting($doiId, 'crossrefconferenceplugin_failedMsg'));
        $this->assertSame(0, DB::table('submission_settings')->where('submission_id', '=', $submissionId)->where('setting_name', 'like', 'crossrefConference::%')->count());
    }

    public function testRegisteredDepositIsCreditedToThePlugin(): void
    {
        [$submissionId, $doiId] = $this->insertPaperWithDoi();
        $this->insertLegacySubmissionSettings($submissionId, ['crossrefConference::status' => 'registered']);

        $this->runMigration();

        $this->assertSame(Doi::STATUS_REGISTERED, (int) DB::table('dois')->where('doi_id', '=', $doiId)->value('status'));
        $this->assertSame('CrossrefConferenceExportPlugin', $this->doiSetting($doiId, 'registrationAgency'));
    }

    public function testDoiAlreadyHandledByAnotherAgencyIsLeftAlone(): void
    {
        [$submissionId, $doiId] = $this->insertPaperWithDoi(Doi::STATUS_REGISTERED);
        $this->insertLegacySubmissionSettings($submissionId, ['crossrefConference::status' => 'failed']);

        $this->runMigration();

        $this->assertSame(Doi::STATUS_REGISTERED, (int) DB::table('dois')->where('doi_id', '=', $doiId)->value('status'));
    }

    public function testConfiguredJournalKeepsDepositingThroughThePlugin(): void
    {
        $this->insertLegacyPluginSettings([
            'depositorName' => 'Lepidus Tecnologia',
            'depositorEmail' => 'doi@lepidus.com.br',
            'username' => 'lepidus',
            'conferenceNameOption' => 'issueTitle',
            'automaticRegistration' => '1',
        ]);

        $this->runMigration();

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $this->assertSame('Lepidus Tecnologia', $pluginSettingsDao->getSetting($this->contextId, 'crossrefconferenceplugin', 'depositorName'));
        $this->assertSame('issueTitle', $pluginSettingsDao->getSetting($this->contextId, 'crossrefconferenceplugin', 'conferenceNameOption'));
        $this->assertTrue($pluginSettingsDao->getSetting($this->contextId, 'crossrefconferenceplugin', 'enabled'));
        $this->assertSame('crossrefconferenceplugin', $this->contextSetting(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY));
        $this->assertSame('1', $this->contextSetting(Context::SETTING_DOI_AUTOMATIC_DEPOSIT));
        $this->assertSame(['publication'], json_decode($this->contextSetting(Context::SETTING_ENABLED_DOI_TYPES), true));
        $this->assertSame(0, DB::table('plugin_settings')->where('plugin_name', '=', 'crossrefconferenceexportplugin')->count());
    }

    public function testPlainTextPasswordIsStoredEncrypted(): void
    {
        $this->insertLegacyPluginSettings(['depositorName' => 'Lepidus Tecnologia', 'password' => 'crossref-password']);

        $this->runMigration();

        $storedPassword = DB::table('plugin_settings')
            ->where('context_id', '=', $this->contextId)
            ->where('plugin_name', '=', 'crossrefconferenceplugin')
            ->where('setting_name', '=', 'password')
            ->value('setting_value');
        $this->assertStringStartsWith('base64:', $storedPassword);
        $this->assertSame('crossref-password', $this->plugin->getDepositPassword($this->contextId));
    }

    public function testJournalWithAnotherAgencyKeepsIt(): void
    {
        $this->setContextSetting(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, 'crossrefplugin');
        $this->insertLegacyPluginSettings(['depositorName' => 'Lepidus Tecnologia']);

        $this->runMigration();

        $this->assertSame('crossrefplugin', $this->contextSetting(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY));
    }

    public function testLegacyFiltersAreRemoved(): void
    {
        $filterGroupId = DB::table('filter_groups')->where('symbolic', '=', 'paper=>crossref-xml')->value('filter_group_id');
        DB::table('filters')->insert([
            'filter_group_id' => $filterGroupId,
            'context_id' => null,
            'display_name' => 'Crossref XML paper export',
            'class_name' => 'plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter',
            'is_template' => 0,
            'parent_filter_id' => null,
            'seq' => 0,
        ]);

        $this->runMigration();

        $this->assertSame(0, DB::table('filters')->whereIn('class_name', CrossrefConferenceUpgradeMigration::LEGACY_FILTER_CLASSES)->count());
    }

    private function runMigration(): void
    {
        (new CrossrefConferenceUpgradeMigration($this->plugin))->up();
    }

    private function insertPaperWithDoi(int $doiStatus = Doi::STATUS_UNREGISTERED): array
    {
        $doiId = DB::table('dois')->insertGetId(['context_id' => $this->contextId, 'doi' => '10.5540/' . uniqid(), 'status' => $doiStatus], 'doi_id');
        $submissionId = DB::table('submissions')->insertGetId(['context_id' => $this->contextId, 'status' => 3], 'submission_id');
        $publicationId = DB::table('publications')->insertGetId(['submission_id' => $submissionId, 'status' => 3, 'doi_id' => $doiId], 'publication_id');
        DB::table('submissions')->where('submission_id', '=', $submissionId)->update(['current_publication_id' => $publicationId]);

        return [$submissionId, $doiId];
    }

    private function insertLegacySubmissionSettings(int $submissionId, array $settings): void
    {
        foreach ($settings as $name => $value) {
            DB::table('submission_settings')->insert(['submission_id' => $submissionId, 'locale' => '', 'setting_name' => $name, 'setting_value' => $value]);
        }
    }

    private function insertLegacyPluginSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            DB::table('plugin_settings')->insert([
                'context_id' => $this->contextId,
                'plugin_name' => 'crossrefconferenceexportplugin',
                'setting_name' => $name,
                'setting_value' => $value,
                'setting_type' => $name === 'automaticRegistration' ? 'bool' : 'string',
            ]);
        }
    }

    private function doiSetting(int $doiId, string $name): ?string
    {
        return DB::table('doi_settings')->where('doi_id', '=', $doiId)->where('setting_name', '=', $name)->value('setting_value');
    }

    private function contextSetting(string $name): ?string
    {
        $contextDao = Application::getContextDAO();
        return DB::table($contextDao->settingsTableName)
            ->where($contextDao->primaryKeyColumn, '=', $this->contextId)
            ->where('setting_name', '=', $name)
            ->value('setting_value');
    }

    private function setContextSetting(string $name, string $value): void
    {
        $contextDao = Application::getContextDAO();
        DB::table($contextDao->settingsTableName)->updateOrInsert(
            [$contextDao->primaryKeyColumn => $this->contextId, 'locale' => '', 'setting_name' => $name],
            ['setting_value' => $value]
        );
    }
}
