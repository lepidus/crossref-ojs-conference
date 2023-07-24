<?php

/**
 * @file plugins/importexport/crossrefConference/CrossrefConferenceExportPlugin.inc.php
 *
 * @class CrossrefConferenceExportPlugin
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief CrossRefConference/MEDLINE XML metadata export plugin
 */

import('classes.plugins.DOIPubIdExportPlugin');

define('CROSSREF_CONFERENCE_STATUS_FAILED', 'failed');
define('CROSSREF_CONFERENCE_API_DEPOSIT_OK', 200);
define('CROSSREF_CONFERENCE_API_DEPOSIT_ERROR_FROM_CROSSREF', 403);
define('CROSSREF_CONFERENCE_API_URL', 'https://api.crossref.org/v2/deposits');
define('CROSSREF_CONFERENCE_API_URL_DEV', 'https://test.crossref.org/v2/deposits');
define('CROSSREF_CONFERENCE_API_STATUS_URL', 'https://api.crossref.org/servlet/submissionDownload');
define('CROSSREF_CONFERENCE_API_STATUS_URL_DEV', 'https://test.crossref.org/servlet/submissionDownload');
define('CROSSREF_CONFERENCE_DEPOSIT_STATUS', 'depositStatus');

class CrossrefConferenceExportPlugin extends DOIPubIdExportPlugin
{
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

    public function getStatusNames()
    {
        return array_merge(parent::getStatusNames(), array(
            EXPORT_STATUS_REGISTERED => __('plugins.importexport.crossrefConference.status.registered'),
            CROSSREF_CONFERENCE_STATUS_FAILED => __('plugins.importexport.crossrefConference.status.failed'),
            EXPORT_STATUS_MARKEDREGISTERED => __('plugins.importexport.crossrefConference.status.markedRegistered'),
        ));
    }

    public function getStatusActions($pubObject)
    {
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        return array(
            CROSSREF_CONFERENCE_STATUS_FAILED =>
                new LinkAction(
                    'failureMessage',
                    new AjaxModal(
                        $dispatcher->url(
                            $request,
                            ROUTE_COMPONENT,
                            null,
                            'grid.settings.plugins.settingsPluginGridHandler',
                            'manage',
                            null,
                            array('plugin' => 'CrossrefConferenceExportPlugin', 'category' => 'importexport', 'verb' => 'statusMessage',
                            'batchId' => $pubObject->getData($this->getDepositBatchIdSettingName()), 'articleId' => $pubObject->getId())
                        ),
                        __('plugins.importexport.crossrefConference.status.failed'),
                        'failureMessage'
                    ),
                    __('plugins.importexport.crossrefConference.status.failed')
                )
        );
    }

    public function getStatusMessage($request)
    {
        $articleId = $request->getUserVar('articleId');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
        $article = $submissionDao->getByid($articleId);
        $failedMsg = $article->getData($this->getFailedMsgSettingName());
        if (!empty($failedMsg)) {
            return $failedMsg;
        }

        $context = $request->getContext();

        $httpClient = Application::get()->getHttpClient();
        try {
            $response = $httpClient->request(
                'POST',
                $this->isTestMode($context) ? CROSSREF_CONFERENCE_API_STATUS_URL_DEV : CROSSREF_CONFERENCE_API_STATUS_URL,
                [
                    'form_params' => [
                        'doi_batch_id' => $request->getUserVar('batchId'),
                        'type' => 'result',
                        'usr' => $this->getSetting($context->getId(), 'username'),
                        'pwd' => $this->getSetting($context->getId(), 'password'),
                    ]
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $returnMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $returnMessage = $e->getResponse()->getBody(true) . ' (' .$e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase() . ')';
            }
            return __('plugins.importexport.common.register.error.mdsError', array('param' => $returnMessage));
        }

        return (string) $response->getBody();
    }

    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
                $this->import('classes.form.CrossrefConferenceDataForm');
                $form = new CrossrefConferenceDataForm($this, $request->getContext()->getId());
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        $notificationManager = new NotificationManager();
                        $notificationManager->createTrivialNotification($request->getUser()->getId());
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }

        return parent::manage($args, $request);
    }

    public function getExportActionNames()
    {
        return array(
            EXPORT_ACTION_DEPOSIT => __('plugins.importexport.crossrefConference.action.register'),
            EXPORT_ACTION_EXPORT => __('plugins.importexport.crossrefConference.action.export'),
            EXPORT_ACTION_MARKREGISTERED => __('plugins.importexport.crossrefConference.action.markRegistered'),
        );
    }

    protected function _getObjectAdditionalSettings()
    {
        return array_merge(parent::_getObjectAdditionalSettings(), array(
            $this->getDepositBatchIdSettingName(),
            $this->getFailedMsgSettingName(),
        ));
    }

    public function getPluginSettingsPrefix()
    {
        return 'crossrefConference';
    }

    public function getSettingsFormClassName()
    {
        return 'CrossrefConferenceSettingsForm';
    }

    public function getConfereceDataFormClassName()
    {
        return 'CrossrefConferenceDataForm';
    }

    public function getExportDeploymentClassName()
    {
        return 'CrossrefConferenceExportDeployment';
    }

    public function executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation = null)
    {
        $context = $request->getContext();
        $path = array('plugin', $this->getName());

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $resultErrors = array();

        if ($request->getUserVar(EXPORT_ACTION_DEPOSIT)) {
            assert($filter != null);
            $errorsOccured = false;
            foreach ($objects as $object) {
                $exportXml = $this->exportXML(array($object), $filter, $context, $noValidation);
                $objectsFileNamePart = $objectsFileNamePart . '-' . $object->getId();
                $exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePart, $context, '.xml');
                $fileManager->writeFile($exportFileName, $exportXml);
                $result = $this->depositXML($object, $context, $exportFileName);
                if (!$result) {
                    $errorsOccured = true;
                }
                if (is_array($result)) {
                    $resultErrors[] = $result;
                }
                $fileManager->deleteByPath($exportFileName);
            }
            if (empty($resultErrors)) {
                if ($errorsOccured) {
                    $this->_sendNotification(
                        $request->getUser(),
                        'plugins.importexport.crossrefConference.register.error.mdsError',
                        NOTIFICATION_TYPE_ERROR
                    );
                } else {
                    $this->_sendNotification(
                        $request->getUser(),
                        $this->getDepositSuccessNotificationMessageKey(),
                        NOTIFICATION_TYPE_SUCCESS
                    );
                }
            } else {
                foreach($resultErrors as $errors) {
                    foreach ($errors as $error) {
                        assert(is_array($error) && count($error) >= 1);
                        $this->_sendNotification(
                            $request->getUser(),
                            $error[0],
                            NOTIFICATION_TYPE_ERROR,
                            (isset($error[1]) ? $error[1] : null)
                        );
                    }
                }
            }
            $request->redirect(null, null, null, $path, null, $tab);
        } else {
            parent::executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation);
        }
    }

    public function depositXML($objects, $context, $filename)
    {
        $status = null;
        $msg = null;

        $httpClient = Application::get()->getHttpClient();
        assert(is_readable($filename));
        try {
            $response = $httpClient->request(
                'POST',
                $this->isTestMode($context) ? CROSSREF_CONFERENCE_API_URL_DEV : CROSSREF_CONFERENCE_API_URL,
                [
                    'multipart' => [
                        [
                            'name'     => 'usr',
                            'contents' => $this->getSetting($context->getId(), 'username'),
                        ],
                        [
                            'name'     => 'pwd',
                            'contents' => $this->getSetting($context->getId(), 'password'),
                        ],
                        [
                            'name'     => 'operation',
                            'contents' => 'doMDUpload',
                        ],
                        [
                            'name'     => 'mdFile',
                            'contents' => fopen($filename, 'r'),
                        ],
                    ]
                ]
            );

        } catch (GuzzleHttp\Exception\RequestException $e) {
            $returnMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $eResponseBody = $e->getResponse()->getBody(true);
                $eStatusCode = $e->getResponse()->getStatusCode();
                if ($eStatusCode == CROSSREF_CONFERENCE_API_DEPOSIT_ERROR_FROM_CROSSREF) {
                    $xmlDoc = new DOMDocument();
                    $xmlDoc->loadXML($eResponseBody);
                    $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
                    $msg = $xmlDoc->getElementsByTagName('msg')->item(0)->nodeValue;
                    $msgSave = $msg . PHP_EOL . $eResponseBody;
                    $status = CROSSREF_CONFERENCE_STATUS_FAILED;
                    $this->updateDepositStatus($context, $objects, $status, $batchIdNode->nodeValue, $msgSave);
                    $this->updateObject($objects);
                    $returnMessage = $msg . ' (' .$eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                } else {
                    $returnMessage = $eResponseBody . ' (' .$eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                }
            }
            return [['plugins.importexport.common.register.error.mdsError', $returnMessage]];
        }

        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($response->getBody());
        $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);

        $failureCountNode = $xmlDoc->getElementsByTagName('failure_count')->item(0);
        $failureCount = (int) $failureCountNode->nodeValue;
        if ($failureCount > 0) {
            $status = CROSSREF_CONFERENCE_STATUS_FAILED;
            $result = false;
        } else {
            $status = EXPORT_STATUS_REGISTERED;
            $result = true;
            $warningCountNode = $xmlDoc->getElementsByTagName('warning_count')->item(0);
            $warningCount = (int) $warningCountNode->nodeValue;
            if ($warningCount > 0) {
                $result = array(array('plugins.importexport.crossrefConference.register.success.warning', htmlspecialchars($response->getBody())));
            }
            HookRegistry::call('crossrefconferenceexportplugin::deposited', array($this, $response->getBody(), $objects));
        }

        if ($status) {
            $this->updateDepositStatus($context, $objects, $status, $batchIdNode->nodeValue, $msgSave);
            $this->updateObject($objects);
        }

        return $result;
    }

    public function updateDepositStatus($context, $object, $status, $batchId, $failedMsg = null)
    {
        assert(is_a($object, 'Submission') or is_a($object, 'Issue'));
        $object->setData($this->getFailedMsgSettingName(), null);
        $object->setData($this->getDepositStatusSettingName(), $status);
        $object->setData($this->getDepositBatchIdSettingName(), $batchId);
        if ($failedMsg) {
            $object->setData($this->getFailedMsgSettingName(), $failedMsg);
        }
        if ($status == EXPORT_STATUS_REGISTERED) {
            $this->saveRegisteredDoi($context, $object);
        }
    }

    public function markRegistered($context, $objects)
    {
        foreach ($objects as $object) {
            $object->setData($this->getFailedMsgSettingName(), null);
            $object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
            $this->saveRegisteredDoi($context, $object);
        }
    }

    public function getFailedMsgSettingName()
    {
        return $this->getPluginSettingsPrefix().'::failedMsg';
    }

    public function getDepositBatchIdSettingName()
    {
        return $this->getPluginSettingsPrefix().'::batchId';
    }

    public function getDepositSuccessNotificationMessageKey()
    {
        return 'plugins.importexport.common.register.success';
    }

    public function executeCLICommand($scriptName, $command, $context, $outputFile, $objects, $filter, $objectsFileNamePart)
    {
        switch ($command) {
            case 'export':
                PluginRegistry::loadCategory('generic', true, $context->getId());
                $exportXml = $this->exportXML($objects, $filter, $context);
                if ($outputFile) {
                    file_put_contents($outputFile, $exportXml);
                }
                break;
            case 'register':
                PluginRegistry::loadCategory('generic', true, $context->getId());
                import('lib.pkp.classes.file.FileManager');
                $fileManager = new FileManager();
                $resultErrors = array();
                $errorsOccured = false;
                foreach ($objects as $object) {
                    $exportXml = $this->exportXML(array($object), $filter, $context);
                    $objectsFileNamePartId = $objectsFileNamePart . '-' . $object->getId();
                    $exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePartId, $context, '.xml');
                    $fileManager->writeFile($exportFileName, $exportXml);
                    $result = $this->depositXML($object, $context, $exportFileName);
                    if (!$result) {
                        $errorsOccured = true;
                    }
                    if (is_array($result)) {
                        $resultErrors[] = $result;
                    }
                    $fileManager->deleteByPath($exportFileName);
                }
                if (empty($resultErrors)) {
                    if ($errorsOccured) {
                        echo __('plugins.importexport.crossrefConference.register.error.mdsError') . "\n";
                    } else {
                        echo __('plugins.importexport.common.register.success') . "\n";
                    }
                } else {
                    echo __('plugins.importexport.common.cliError') . "\n";
                    foreach($resultErrors as $errors) {
                        foreach ($errors as $error) {
                            assert(is_array($error) && count($error) >= 1);
                            $errorMessage = __($error[0], array('param' => (isset($error[1]) ? $error[1] : null)));
                            echo "*** $errorMessage\n";
                        }
                    }
                    echo "\n";
                    $this->usage($scriptName);
                }
                break;
        }
    }
}
