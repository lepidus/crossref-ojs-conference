<?php

namespace APP\plugins\generic\crossrefConference;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\crossrefConference\classes\CrossrefConferenceSettings;
use APP\plugins\generic\crossrefConference\classes\migration\CrossrefConferenceUpgradeMigration;
use APP\plugins\IDoiRegistrationAgency;
use Illuminate\Support\Collection;
use PKP\config\Config;
use PKP\context\Context;
use PKP\doi\Doi;
use PKP\doi\RegistrationAgencySettings;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\services\PKPSchemaService;

class CrossrefConferencePlugin extends GenericPlugin implements IDoiRegistrationAgency
{
    private ?CrossrefConferenceSettings $settingsObject = null;
    private ?CrossrefConferenceExportPlugin $exportPlugin = null;
    private static bool $doiSchemaExtended = false;

    public function getName()
    {
        return 'crossrefconferenceplugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.crossrefConference.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.crossrefConference.description');
    }

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || Application::isUnderMaintenance()) {
            return $success;
        }

        PluginRegistry::register('importexport', new CrossrefConferenceExportPlugin($this), $this->getPluginPath());
        $this->exportPlugin = PluginRegistry::getPlugin('importexport', 'CrossrefConferenceExportPlugin');

        if ($this->getEnabled($mainContextId)) {
            $this->registerHooks();
        }

        return $success;
    }

    public function getInstallMigration()
    {
        return new CrossrefConferenceUpgradeMigration($this);
    }

    public function setEnabled($enabled)
    {
        parent::setEnabled($enabled);
        if ($enabled) {
            return;
        }

        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->getCurrentContextId());
        if ($context?->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY) === $this->getName()) {
            $context->setData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, Context::SETTING_NO_REGISTRATION_AGENCY);
            $contextDao->updateObject($context);
        }
    }

    public function __wakeup(): void
    {
        $this->settingsObject = null;
    }

    private function registerHooks(): void
    {
        Hook::add('DoiSettingsForm::setEnabledRegistrationAgencies', $this->addAsRegistrationAgencyOption(...));
        Hook::add('DoiSetupSettingsForm::getObjectTypes', $this->addAllowedObjectTypes(...));
        Hook::add('Context::validate', $this->validateAllowedPubObjectTypes(...));
        Hook::add('Doi::markRegistered', $this->editMarkRegisteredParams(...));
        Hook::add('DoiListPanel::setConfig', $this->addRegistrationAgencyName(...));
        $this->extendDoiSchema();
    }

    private function extendDoiSchema(): void
    {
        if (self::$doiSchemaExtended) {
            return;
        }
        self::$doiSchemaExtended = true;

        $this->addLocaleData();
        Hook::add('Schema::get::doi', $this->addToSchema(...));
        app()->get('schema')->get(PKPSchemaService::SCHEMA_DOI, true);
    }

    public function addToSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        foreach ([$this->getDepositBatchIdSettingName(), $this->getFailedMsgSettingName()] as $settingName) {
            $schema->properties->{$settingName} = (object) [
                'type' => 'string',
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return Hook::CONTINUE;
    }

    public function addAsRegistrationAgencyOption(string $hookName, array $args)
    {
        /** @var Collection<int,IDoiRegistrationAgency> $enabledRegistrationAgencies */
        $enabledRegistrationAgencies = &$args[0];
        $enabledRegistrationAgencies->add($this);

        return Hook::CONTINUE;
    }

    public function addRegistrationAgencyName(string $hookName, array $args): bool
    {
        $config = &$args[0];
        $config['registrationAgencyNames'][$this->getExportPlugin()->getName()] = $this->getRegistrationAgencyName();

        return Hook::CONTINUE;
    }

    public function addAllowedObjectTypes(string $hookName, array $args): bool
    {
        $objectTypeOptions = &$args[0];
        $allowedTypes = $this->getAllowedDoiTypes();

        $objectTypeOptions = array_map(function ($option) use ($allowedTypes) {
            if (in_array($option['value'], $allowedTypes)) {
                $option['allowedBy'][] = $this->getName();
            }
            return $option;
        }, $objectTypeOptions);

        return Hook::CONTINUE;
    }

    public function validateAllowedPubObjectTypes(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $props = $args[2];

        if (!isset($props['enabledDoiTypes']) || empty($props['id'])) {
            return Hook::CONTINUE;
        }

        $context = app()->get('context')->get($props['id']);
        if (!$context?->getConfiguredDoiAgency() instanceof $this) {
            return Hook::CONTINUE;
        }

        if (!empty(array_diff($props['enabledDoiTypes'], $this->getAllowedDoiTypes()))) {
            $errors['enabledDoiTypes'] = [__('doi.manager.settings.enabledDoiTypes.error')];
        }

        return Hook::CONTINUE;
    }

    public function editMarkRegisteredParams(string $hookName, array $args): bool
    {
        $editParams = &$args[0];
        $editParams[$this->getFailedMsgSettingName()] = null;

        return Hook::CONTINUE;
    }

    public function isPluginConfigured(Context $context): bool
    {
        $settingsObject = $this->getSettingsObject();

        /** @var PKPSchemaService $schemaService */
        $schemaService = app()->get('schema');
        foreach ($schemaService->getRequiredProps($settingsObject::class) as $requiredProp) {
            if (empty($this->getSetting($context->getId(), $requiredProp))) {
                return false;
            }
        }

        if (empty($context->getData(Context::SETTING_DOI_PREFIX))) {
            return false;
        }

        return $context->getData('publisherInstitution')
            && ($context->getData('onlineIssn') || $context->getData('printIssn'));
    }

    public function getRegistrationAgencyName(): string
    {
        return __('plugins.importexport.crossrefConference.registrationAgency.name');
    }

    public function exportSubmissions(array $submissions, Context $context): array
    {
        $this->extendDoiSchema();
        $exportPlugin = $this->getExportPlugin();
        $xmlErrors = [];
        $temporaryFileId = $exportPlugin->exportAsDownload($context, $submissions, $exportPlugin->getSubmissionFilter(), 'articles', null, $xmlErrors);

        return ['temporaryFileId' => $temporaryFileId, 'xmlErrors' => $xmlErrors];
    }

    public function depositSubmissions(array $submissions, Context $context): array
    {
        $this->extendDoiSchema();
        $exportPlugin = $this->getExportPlugin();

        $refusal = match (true) {
            (bool) Config::getVar('general', 'sandbox', false) => 'common.sandbox',
            !$this->isPluginConfigured($context) => 'plugins.importexport.common.error.pluginNotConfigured',
            default => null,
        };
        if ($refusal) {
            foreach ($submissions as $submission) {
                $exportPlugin->updateDepositStatus($context, $submission, Doi::STATUS_ERROR, null, __($refusal));
            }
            return ['hasErrors' => true, 'responseMessage' => $refusal];
        }

        $responseMessage = '';
        $status = $exportPlugin->exportAndDeposit($context, $submissions, $exportPlugin->getSubmissionFilter(), $responseMessage);

        return [
            'hasErrors' => !$status,
            'responseMessage' => $responseMessage,
        ];
    }

    public function exportIssues(array $issues, Context $context): array
    {
        return ['temporaryFileId' => null, 'xmlErrors' => [__('plugins.importexport.crossrefConference.issues.notSupported')]];
    }

    public function depositIssues(array $issues, Context $context): array
    {
        return [
            'hasErrors' => true,
            'responseMessage' => 'plugins.importexport.crossrefConference.issues.notSupported',
        ];
    }

    public function getFailedMsgSettingName(): string
    {
        return $this->getName() . '_failedMsg';
    }

    public function getDepositBatchIdSettingName(): string
    {
        return $this->getName() . '_batchId';
    }

    public function getErrorMessageKey(): ?string
    {
        return $this->getFailedMsgSettingName();
    }

    public function getRegisteredMessageKey(): ?string
    {
        return null;
    }

    public function getSettingsObject(): RegistrationAgencySettings
    {
        return $this->settingsObject ??= new CrossrefConferenceSettings($this);
    }

    public function getAllowedDoiTypes(): array
    {
        return [Repo::doi()::TYPE_PUBLICATION];
    }

    public function getExportPlugin(): CrossrefConferenceExportPlugin
    {
        if ($this->exportPlugin === null) {
            $this->exportPlugin = PluginRegistry::getPlugin('importexport', 'CrossrefConferenceExportPlugin')
                ?? new CrossrefConferenceExportPlugin($this);
        }
        return $this->exportPlugin;
    }
}
