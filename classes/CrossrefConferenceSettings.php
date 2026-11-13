<?php

namespace APP\plugins\generic\crossrefConference\classes;

use APP\core\Application;
use Illuminate\Validation\Validator;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\doi\RegistrationAgencySettings;

class CrossrefConferenceSettings extends RegistrationAgencySettings
{
    public const CONFERENCE_NAME_FROM_ISSUE_TITLE = 'issueTitle';
    public const CONFERENCE_NAME_CUSTOM = 'custom';

    public function getSchema(): \stdClass
    {
        return (object) [
            'title' => 'Crossref Conference Plugin',
            'description' => 'Registration agency plugin for Crossref conference proceedings',
            'type' => 'object',
            'required' => ['depositorName', 'depositorEmail', 'conferenceNameOption'],
            'properties' => (object) [
                'depositorName' => (object) [
                    'type' => 'string',
                    'validation' => ['required', 'max:60'],
                ],
                'depositorEmail' => (object) [
                    'type' => 'string',
                    'validation' => ['required', 'email', 'max:90'],
                ],
                'username' => (object) [
                    'type' => 'string',
                    'validation' => ['nullable', 'max:50'],
                ],
                'password' => (object) [
                    'type' => 'string',
                    'validation' => ['nullable', 'max:50'],
                ],
                'testMode' => (object) [
                    'type' => 'boolean',
                ],
                'conferenceNameOption' => (object) [
                    'type' => 'string',
                    'validation' => ['required', 'in:' . self::CONFERENCE_NAME_FROM_ISSUE_TITLE . ',' . self::CONFERENCE_NAME_CUSTOM],
                ],
                'conferenceName' => (object) [
                    'type' => 'string',
                    'validation' => ['nullable', 'max:255'],
                ],
            ],
        ];
    }

    protected function addValidationChecks(Validator &$validator, array $props): void
    {
        $validator->after(function (Validator $validator) use ($props) {
            if (($props['conferenceNameOption'] ?? null) === self::CONFERENCE_NAME_CUSTOM && empty($props['conferenceName'])) {
                $validator->errors()->add('conferenceName', __('plugins.importexport.crossrefConference.settings.form.conferenceNameRequired'));
            }
        });
    }

    public function getFields(Context $context): array
    {
        $contextId = $context->getId();

        return [
            new FieldHTML('preamble', [
                'label' => $this->agencyPlugin->getRegistrationAgencyName(),
                'description' => $this->getPreambleText($context),
            ]),
            new FieldText('depositorName', [
                'label' => __('plugins.importexport.crossrefConference.settings.form.depositorName'),
                'isRequired' => true,
                'value' => $this->agencyPlugin->getSetting($contextId, 'depositorName'),
            ]),
            new FieldText('depositorEmail', [
                'label' => __('plugins.importexport.crossrefConference.settings.form.depositorEmail'),
                'isRequired' => true,
                'value' => $this->agencyPlugin->getSetting($contextId, 'depositorEmail'),
            ]),
            new FieldHTML('credentialsExplanation', [
                'description' => __('plugins.importexport.crossrefConference.registrationIntro'),
            ]),
            new FieldText('username', [
                'label' => __('plugins.importexport.crossrefConference.settings.form.username'),
                'value' => $this->agencyPlugin->getSetting($contextId, 'username'),
            ]),
            new FieldText('password', [
                'label' => __('plugins.importexport.common.settings.form.password'),
                'description' => __('plugins.importexport.common.settings.form.password.description'),
                'value' => $this->agencyPlugin->getSetting($contextId, 'password'),
                'inputType' => 'password',
            ]),
            new FieldOptions('testMode', [
                'label' => __('plugins.importexport.common.settings.form.testMode.label'),
                'options' => [
                    ['value' => true, 'label' => __('plugins.importexport.crossrefConference.settings.form.testMode.description')],
                ],
                'value' => (bool) $this->agencyPlugin->getSetting($contextId, 'testMode'),
            ]),
            new FieldOptions('conferenceNameOption', [
                'label' => __('plugins.importexport.crossrefConference.settings.form.conferenceName'),
                'type' => 'radio',
                'isRequired' => true,
                'options' => [
                    ['value' => self::CONFERENCE_NAME_FROM_ISSUE_TITLE, 'label' => __('plugins.importexport.crossrefConference.settings.form.conferenceNameOptionIssueTitle')],
                    ['value' => self::CONFERENCE_NAME_CUSTOM, 'label' => __('plugins.importexport.crossrefConference.settings.form.conferenceNameOptionCustom')],
                ],
                'value' => $this->agencyPlugin->getSetting($contextId, 'conferenceNameOption'),
            ]),
            new FieldText('conferenceName', [
                'label' => __('plugins.importexport.crossrefConference.settings.conferenceName'),
                'isRequired' => true,
                'showWhen' => ['conferenceNameOption', self::CONFERENCE_NAME_CUSTOM],
                'value' => $this->agencyPlugin->getSetting($contextId, 'conferenceName'),
            ]),
        ];
    }

    protected function getPreambleText(Context $context): string
    {
        $journalSettingsUrl = Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $context->getPath(),
            'management',
            'settings',
            ['context']
        );

        $notices = [];
        if (!$context->getData('publisherInstitution')) {
            $notices[] = __('plugins.importexport.crossrefConference.error.publisherNotConfigured', ['journalSettingsUrl' => $journalSettingsUrl]);
        }

        if (!$context->getData('onlineIssn') && !$context->getData('printIssn')) {
            $notices[] = __('plugins.importexport.crossrefConference.error.issnNotConfigured', ['journalSettingsUrl' => $journalSettingsUrl]);
        }

        $text = '';
        if (!empty($notices)) {
            $text .= '<div class="pkpNotification pkpNotification--warning">';
            $text .= '<p><strong>' . __('plugins.importexport.common.missingRequirements') . '</strong></p><ul>';
            foreach ($notices as $notice) {
                $text .= '<li>' . $notice . '</li>';
            }
            $text .= '</ul></div>';
        }

        $text .= '<p>' . __('plugins.importexport.crossrefConference.settings.depositorIntro') . '</p>';

        return $text;
    }
}
