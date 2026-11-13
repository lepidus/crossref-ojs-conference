<?php

namespace APP\plugins\generic\crossrefConference\classes\migration;

use APP\core\Application;
use APP\plugins\generic\crossrefConference\CrossrefConferencePlugin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\doi\Doi;
use PKP\plugins\PluginSettingsDAO;

class CrossrefConferenceUpgradeMigration extends Migration
{
    public const LEGACY_PLUGIN_NAME = 'crossrefconferenceexportplugin';
    public const LEGACY_STATUS = 'crossrefConference::status';
    public const LEGACY_BATCH_ID = 'crossrefConference::batchId';
    public const LEGACY_FAILED_MESSAGE = 'crossrefConference::failedMsg';
    public const LEGACY_REGISTERED_DOI = 'crossrefConference::registeredDoi';
    public const LEGACY_FILTER_CLASSES = [
        'plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter',
        'plugins.importexport.crossrefConference.filter.ProceedingsCrossrefXmlConferenceFilter',
    ];
    public const MIGRATED_SETTINGS = [
        'depositorName' => 'string',
        'depositorEmail' => 'string',
        'username' => 'string',
        'password' => 'string',
        'testMode' => 'bool',
        'conferenceName' => 'string',
        'conferenceNameOption' => 'string',
    ];

    public function __construct(private CrossrefConferencePlugin $plugin)
    {
    }

    public function up(): void
    {
        DB::transaction(function () {
            $this->migrateDepositStatus();
            $this->migrateSettings();
            $this->removeLegacyFilters();
            $this->retireLegacyVersion();
        });
    }

    public function down(): void
    {
    }

    public static function doiStatusFromLegacy(?string $legacyStatus, ?string $registeredDoi): ?int
    {
        return match ($legacyStatus) {
            'registered', 'markedRegistered' => Doi::STATUS_REGISTERED,
            'failed' => Doi::STATUS_ERROR,
            default => empty($registeredDoi) ? null : Doi::STATUS_REGISTERED,
        };
    }

    private function migrateDepositStatus(): void
    {
        $legacySettingNames = [self::LEGACY_STATUS, self::LEGACY_BATCH_ID, self::LEGACY_FAILED_MESSAGE, self::LEGACY_REGISTERED_DOI];
        $legacySettingsBySubmission = DB::table('submission_settings')
            ->whereIn('setting_name', $legacySettingNames)
            ->get()
            ->groupBy('submission_id');

        foreach ($legacySettingsBySubmission as $submissionId => $settings) {
            $doiId = DB::table('submissions AS s')
                ->join('publications AS p', 'p.publication_id', '=', 's.current_publication_id')
                ->where('s.submission_id', '=', $submissionId)
                ->value('p.doi_id');

            if (!$doiId || DB::table('dois')->where('doi_id', '=', $doiId)->value('status') != Doi::STATUS_UNREGISTERED) {
                continue;
            }

            $legacyStatus = $this->settingValue($settings, self::LEGACY_STATUS);
            $status = self::doiStatusFromLegacy($legacyStatus, $this->settingValue($settings, self::LEGACY_REGISTERED_DOI));
            if ($status === null) {
                continue;
            }

            DB::table('dois')->where('doi_id', '=', $doiId)->update(['status' => $status]);

            $doiSettings = [
                'registrationAgency' => $status === Doi::STATUS_REGISTERED && $legacyStatus !== 'markedRegistered'
                    ? $this->plugin->getExportPlugin()->getName()
                    : null,
                $this->plugin->getDepositBatchIdSettingName() => $this->settingValue($settings, self::LEGACY_BATCH_ID),
                $this->plugin->getFailedMsgSettingName() => $this->settingValue($settings, self::LEGACY_FAILED_MESSAGE),
            ];
            foreach (array_filter($doiSettings) as $settingName => $settingValue) {
                DB::table('doi_settings')->updateOrInsert(
                    ['doi_id' => $doiId, 'locale' => '', 'setting_name' => $settingName],
                    ['setting_value' => $settingValue]
                );
            }
        }

        DB::table('submission_settings')->whereIn('setting_name', $legacySettingNames)->delete();
    }

    private function migrateSettings(): void
    {
        $legacySettingsByContext = DB::table('plugin_settings')
            ->where('plugin_name', '=', self::LEGACY_PLUGIN_NAME)
            ->get()
            ->groupBy('context_id');

        /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginName = $this->plugin->getName();

        foreach ($legacySettingsByContext as $contextId => $settings) {
            $contextId = (int) $contextId;
            foreach (self::MIGRATED_SETTINGS as $settingName => $settingType) {
                $value = $this->settingValue($settings, $settingName);
                if ($value === null || $pluginSettingsDao->getSetting($contextId, $pluginName, $settingName) !== null) {
                    continue;
                }
                if ($settingName === 'password') {
                    $this->plugin->updateSetting($contextId, $settingName, $value, $settingType);
                    continue;
                }
                $pluginSettingsDao->updateSetting($contextId, $pluginName, $settingName, $settingType === 'bool' ? (bool) $value : $value, $settingType);
            }

            if ($this->settingValue($settings, 'depositorName') === null) {
                continue;
            }

            $pluginSettingsDao->updateSetting($contextId, $pluginName, 'enabled', true, 'bool');
            $this->selectAsRegistrationAgency($contextId, (bool) $this->settingValue($settings, 'automaticRegistration'));
        }

        DB::table('plugin_settings')->where('plugin_name', '=', self::LEGACY_PLUGIN_NAME)->delete();
    }

    private function selectAsRegistrationAgency(int $contextId, bool $automaticDeposit): void
    {
        $contextDao = Application::getContextDAO();
        $contextSetting = fn (string $name) => DB::table($contextDao->settingsTableName)
            ->where($contextDao->primaryKeyColumn, '=', $contextId)
            ->where('setting_name', '=', $name)
            ->value('setting_value');
        $updateContextSetting = fn (string $name, string $value) => DB::table($contextDao->settingsTableName)->updateOrInsert(
            [$contextDao->primaryKeyColumn => $contextId, 'locale' => '', 'setting_name' => $name],
            ['setting_value' => $value]
        );

        $configuredAgency = $contextSetting(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY);
        if (!$contextSetting(Context::SETTING_ENABLE_DOIS) || !in_array($configuredAgency, [null, '', Context::SETTING_NO_REGISTRATION_AGENCY], true)) {
            return;
        }

        $updateContextSetting(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, $this->plugin->getName());
        if ($automaticDeposit) {
            $updateContextSetting(Context::SETTING_DOI_AUTOMATIC_DEPOSIT, '1');
        }

        $enabledDoiTypes = json_decode((string) $contextSetting(Context::SETTING_ENABLED_DOI_TYPES), true);
        if (is_array($enabledDoiTypes)) {
            $updateContextSetting(
                Context::SETTING_ENABLED_DOI_TYPES,
                json_encode(array_values(array_intersect($enabledDoiTypes, $this->plugin->getAllowedDoiTypes())))
            );
        }
    }

    private function removeLegacyFilters(): void
    {
        $legacyFilterIds = DB::table('filters')->whereIn('class_name', self::LEGACY_FILTER_CLASSES)->pluck('filter_id');
        DB::table('filter_settings')->whereIn('filter_id', $legacyFilterIds)->delete();
        DB::table('filters')->whereIn('filter_id', $legacyFilterIds)->delete();
    }

    private function retireLegacyVersion(): void
    {
        DB::table('versions')
            ->where('product_type', '=', 'plugins.importexport')
            ->where('product', '=', 'crossrefConference')
            ->update(['current' => 0]);
    }

    private function settingValue(Collection $settings, string $name): ?string
    {
        $value = $settings->firstWhere('setting_name', $name)?->setting_value;
        return $value === null || $value === '' ? null : $value;
    }
}
