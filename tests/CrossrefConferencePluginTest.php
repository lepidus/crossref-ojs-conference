<?php

require_once __DIR__ . '/CrossrefConferenceTestCase.php';

use APP\facades\Repo;
use APP\plugins\generic\crossrefConference\CrossrefConferenceExportPlugin;
use APP\plugins\generic\crossrefConference\CrossrefConferencePlugin;
use PKP\context\Context;
use PKP\core\DataObject;
use PKP\doi\Doi;

class CrossrefConferencePluginTest extends CrossrefConferenceTestCase
{
    private function createConfiguredJournal(): \APP\journal\Journal
    {
        $journal = $this->createJournal();
        $journal->setData(Context::SETTING_DOI_PREFIX, '10.5540');
        return $journal;
    }

    public function testOnlyPublicationDoisAreRegistered(): void
    {
        $this->assertSame([Repo::doi()::TYPE_PUBLICATION], $this->createAgencyPlugin()->getAllowedDoiTypes());
    }

    public function testIsConfiguredWithDepositorConferenceNameAndJournalData(): void
    {
        $this->assertTrue($this->createAgencyPlugin()->isPluginConfigured($this->createConfiguredJournal()));
    }

    public function testIsNotConfiguredWithoutConferenceNameOption(): void
    {
        unset($this->pluginSettings['conferenceNameOption']);

        $this->assertFalse($this->createAgencyPlugin()->isPluginConfigured($this->createConfiguredJournal()));
    }

    public function testIsNotConfiguredWithoutDoiPrefix(): void
    {
        $this->assertFalse($this->createAgencyPlugin()->isPluginConfigured($this->createJournal()));
    }

    public function testIsNotConfiguredWithoutJournalIssn(): void
    {
        $journal = $this->createConfiguredJournal();
        $journal->setData('onlineIssn', null);

        $this->assertFalse($this->createAgencyPlugin()->isPluginConfigured($journal));
    }

    public function testCustomConferenceNameIsRequiredWhenChosen(): void
    {
        $errors = $this->createAgencyPlugin()->getSettingsObject()->validate([
            'depositorName' => 'Lepidus Tecnologia',
            'depositorEmail' => 'doi@lepidus.com.br',
            'conferenceNameOption' => 'custom',
            'conferenceName' => '',
        ]);

        $this->assertArrayHasKey('conferenceName', $errors);
    }

    public function testIssueTitleOptionNeedsNoCustomConferenceName(): void
    {
        $errors = $this->createAgencyPlugin()->getSettingsObject()->validate([
            'depositorName' => 'Lepidus Tecnologia',
            'depositorEmail' => 'doi@lepidus.com.br',
            'conferenceNameOption' => 'issueTitle',
        ]);

        $this->assertSame([], $errors);
    }

    public function testIssueDepositIsRefused(): void
    {
        $result = $this->createAgencyPlugin()->depositIssues([$this->createIssue()], $this->journal);

        $this->assertTrue($result['hasErrors']);
    }

    public function testDepositIsRefusedWhileThePluginIsNotConfigured(): void
    {
        $exportPlugin = new class ($this->createAgencyPlugin()) extends CrossrefConferenceExportPlugin {
            public array $recordedStatuses = [];

            public function updateDepositStatus(Context $context, DataObject $object, int $status, ?string $batchId = null, ?string $failedMessage = null): void
            {
                $this->recordedStatuses[] = [$object->getId(), $status, $failedMessage];
            }
        };
        $agencyPlugin = new class ($this, $exportPlugin) extends CrossrefConferencePlugin {
            public function __construct(private CrossrefConferenceTestCase $testCase, private CrossrefConferenceExportPlugin $recordingExportPlugin)
            {
                parent::__construct();
            }

            public function getSetting($contextId, $name)
            {
                return $this->testCase->getPluginSetting($name);
            }

            public function getExportPlugin(): CrossrefConferenceExportPlugin
            {
                return $this->recordingExportPlugin;
            }
        };

        $result = $agencyPlugin->depositSubmissions([$this->createSubmission()], $this->journal);

        $this->assertTrue($result['hasErrors']);
        $this->assertSame('plugins.importexport.common.error.pluginNotConfigured', $result['responseMessage']);
        $this->assertSame([[2680, Doi::STATUS_ERROR, __('plugins.importexport.common.error.pluginNotConfigured')]], $exportPlugin->recordedStatuses);
    }

    public function testDepositMessagesAreStoredOnTheDoiUnderThePluginName(): void
    {
        $plugin = $this->createAgencyPlugin();

        $this->assertSame('crossrefconferenceplugin_failedMsg', $plugin->getErrorMessageKey());
        $this->assertSame('crossrefconferenceplugin_batchId', $plugin->getDepositBatchIdSettingName());
    }
}
