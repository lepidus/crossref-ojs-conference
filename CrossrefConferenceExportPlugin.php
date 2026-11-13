<?php

namespace APP\plugins\generic\crossrefConference;

use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\DOIPubIdExportPlugin;
use APP\submission\Submission;
use DOMDocument;
use Exception;
use GuzzleHttp\Exception\RequestException;
use PKP\context\Context;
use PKP\core\DataObject;
use PKP\doi\Doi;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\scheduledTask\PKPScheduler;

class CrossrefConferenceExportPlugin extends DOIPubIdExportPlugin
{
    public const CROSSREF_CONFERENCE_API_DEPOSIT_ERROR_FROM_CROSSREF = 403;
    public const CROSSREF_CONFERENCE_API_URL = 'https://api.crossref.org/v2/deposits';
    public const CROSSREF_CONFERENCE_API_URL_DEV = 'https://test.crossref.org/v2/deposits';

    public function __construct(protected CrossrefConferencePlugin $agencyPlugin)
    {
        parent::__construct();
    }

    public function getName()
    {
        return 'CrossrefConferenceExportPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.crossrefConference.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.crossrefConference.description');
    }

    public function getSubmissionFilter()
    {
        return 'paper=>crossref-xml';
    }

    public function getSetting($contextId, $name)
    {
        return $this->agencyPlugin->getSetting($contextId, $name);
    }

    public function getPluginSettingsPrefix()
    {
        return $this->agencyPlugin->getName();
    }

    public function getSettingsFormClassName()
    {
        throw new Exception('DOI settings no longer managed via plugin settings form.');
    }

    public function getExportDeploymentClassName()
    {
        return CrossrefConferenceExportDeployment::class;
    }

    public function getFailedMsgSettingName(): string
    {
        return $this->agencyPlugin->getFailedMsgSettingName();
    }

    public function getDepositBatchIdSettingName(): string
    {
        return $this->agencyPlugin->getDepositBatchIdSettingName();
    }

    public function getDepositSuccessNotificationMessageKey()
    {
        return 'plugins.importexport.common.register.success';
    }

    public function exportAndDeposit(Context $context, array $objects, string $filter, string &$responseMessage, ?bool $noValidation = null): bool
    {
        $fileManager = new FileManager();
        $resultErrors = [];
        $errorsOccurred = false;

        foreach ($objects as $object) {
            $exportErrors = [];
            $exportXml = $this->exportXML([$object], $filter, $context, $noValidation, $exportErrors);
            if (!empty($exportErrors)) {
                $this->updateDepositStatus($context, $object, Doi::STATUS_ERROR, null, $this->describeXmlErrors($exportErrors));
                $errorsOccurred = true;
                continue;
            }
            $exportFileName = $this->getExportFileName($this->getExportPath(), 'articles-' . $object->getId(), $context, '.xml');
            $fileManager->writeFile($exportFileName, $exportXml);
            $result = $this->depositXML($object, $context, $exportFileName);
            if (!$result) {
                $errorsOccurred = true;
            }
            if (is_array($result)) {
                $resultErrors[] = $result;
            }
            $fileManager->deleteByPath($exportFileName);
        }

        if (!empty($resultErrors)) {
            $responseMessage = 'api.dois.400.depositFailed';
            return false;
        }

        if ($errorsOccurred) {
            $responseMessage = 'plugins.importexport.crossrefConference.register.error.mdsError';
            return false;
        }

        $responseMessage = $this->getDepositSuccessNotificationMessageKey();
        return true;
    }

    public function exportAsDownload(Context $context, array $objects, string $filter, string $objectsFileNamePart, ?bool $noValidation = null, ?array &$exportErrors = null): ?int
    {
        $fileManager = new TemporaryFileManager();

        $exportErrors = [];
        $exportXml = $this->exportXML($objects, $filter, $context, $noValidation, $exportErrors);

        $exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePart, $context, '.xml');

        $fileManager->writeFile($exportFileName, $exportXml);

        $user = Application::get()->getRequest()->getUser();

        return $fileManager->createTempFileFromExisting($exportFileName, $user->getId());
    }

    public function depositXML($objects, $context, $filename)
    {
        $httpClient = Application::get()->getHttpClient();
        assert(is_readable($filename));
        try {
            $response = $httpClient->request(
                'POST',
                $this->isTestMode($context) ? static::CROSSREF_CONFERENCE_API_URL_DEV : static::CROSSREF_CONFERENCE_API_URL,
                [
                    'multipart' => [
                        [
                            'name' => 'usr',
                            'contents' => $this->getSetting($context->getId(), 'username'),
                        ],
                        [
                            'name' => 'pwd',
                            'contents' => $this->agencyPlugin->getDepositPassword($context->getId()),
                        ],
                        [
                            'name' => 'operation',
                            'contents' => 'doMDUpload',
                        ],
                        [
                            'name' => 'mdFile',
                            'contents' => fopen($filename, 'r'),
                        ],
                    ]
                ]
            );
        } catch (RequestException $e) {
            $returnMessage = $e->getMessage();
            $batchId = null;
            $failedMessage = $returnMessage;
            if ($e->hasResponse()) {
                $eResponseBody = (string) $e->getResponse()->getBody();
                $eStatusCode = $e->getResponse()->getStatusCode();
                if ($eStatusCode == static::CROSSREF_CONFERENCE_API_DEPOSIT_ERROR_FROM_CROSSREF) {
                    $xmlDoc = new DOMDocument('1.0', 'utf-8');
                    $xmlDoc->loadXML($eResponseBody);
                    $batchId = $xmlDoc->getElementsByTagName('batch_id')->item(0)?->nodeValue;
                    $msg = $xmlDoc->getElementsByTagName('msg')->item(0)?->nodeValue;
                    $failedMessage = $msg . PHP_EOL . $eResponseBody;
                    $returnMessage = $msg . ' (' . $eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                } else {
                    $returnMessage = $eResponseBody . ' (' . $eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                    $failedMessage = $returnMessage;
                }
            }
            $this->updateDepositStatus($context, $objects, Doi::STATUS_ERROR, $batchId, $failedMessage);
            return [['plugins.importexport.common.register.error.mdsError', $returnMessage]];
        }

        $responseBody = (string) $response->getBody();
        $xmlDoc = new DOMDocument('1.0', 'utf-8');
        $xmlDoc->loadXML($responseBody);
        $batchId = $xmlDoc->getElementsByTagName('batch_id')->item(0)?->nodeValue;

        $failureCount = (int) $xmlDoc->getElementsByTagName('failure_count')->item(0)?->nodeValue;
        if ($failureCount > 0) {
            $this->updateDepositStatus($context, $objects, Doi::STATUS_ERROR, $batchId, $responseBody);
            return false;
        }

        $result = true;
        $warningCount = (int) $xmlDoc->getElementsByTagName('warning_count')->item(0)?->nodeValue;
        if ($warningCount > 0) {
            $result = [['plugins.importexport.crossrefConference.register.success.warning', htmlspecialchars($responseBody)]];
        }
        Hook::run('crossrefconferenceexportplugin::deposited', [$this, $responseBody, $objects]);

        $this->updateDepositStatus($context, $objects, Doi::STATUS_REGISTERED, $batchId);

        return $result;
    }

    public function updateDepositStatus(Context $context, DataObject $object, int $status, ?string $batchId = null, ?string $failedMessage = null): void
    {
        assert($object instanceof Submission || $object instanceof Issue);
        $doiIds = $object instanceof Submission
            ? Repo::doi()->getDoisForSubmission($object->getId())
            : Repo::doi()->getDoisForIssue($object->getId(), true);

        foreach ($doiIds as $doiId) {
            $doi = Repo::doi()->get($doiId);

            $editParams = [
                'status' => $status,
                $this->getFailedMsgSettingName() => $failedMessage,
                $this->getDepositBatchIdSettingName() => $batchId,
            ];

            if ($status === Doi::STATUS_REGISTERED) {
                $editParams['registrationAgency'] = $this->getName();
            }

            Repo::doi()->edit($doi, $editParams);
        }
    }

    public function registerSchedules(PKPScheduler $scheduler): void
    {
    }

    private function describeXmlErrors(array $xmlErrors): string
    {
        return implode(PHP_EOL, array_map(fn ($error) => trim($error->message) . ' (line ' . $error->line . ')', $xmlErrors));
    }

    public function markRegistered($context, $objects)
    {
        foreach ($objects as $object) {
            $doiIds = $object instanceof Submission
                ? Repo::doi()->getDoisForSubmission($object->getId())
                : Repo::doi()->getDoisForIssue($object->getId(), true);

            foreach ($doiIds as $doiId) {
                Repo::doi()->markRegistered($doiId);
            }
        }
    }
}
