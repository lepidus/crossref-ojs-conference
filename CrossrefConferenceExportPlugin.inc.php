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

// The status of the Crossref DOI.
// any, notDeposited, and markedRegistered are reserved
define('CROSSREF_STATUS_FAILED', 'failed');

define('CROSSREF_API_DEPOSIT_OK', 200);

define('CROSSREF_API_URL', 'https://api.crossref.org/v2/deposits');
//TESTING
define('CROSSREF_API_URL_DEV', 'https://test.crossref.org/v2/deposits');

define('CROSSREF_API_STATUS_URL', 'https://api.crossref.org/servlet/submissionDownload');
//TESTING
define('CROSSREF_API_STATUS_URL_DEV', 'https://test.crossref.org/servlet/submissionDownload');

// The name of the setting used to save the registered DOI and the URL with the deposit status.
define('CROSSREF_DEPOSIT_STATUS', 'depositStatus');


class CrossrefConferenceExportPlugin extends DOIPubIdExportPlugin {

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'CrossrefConferenceExportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.importexport.crossrefConference.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.importexport.crossrefConference.description');
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSubmissionFilter()
	 */
	function getSubmissionFilter() {
		return 'paper=>crossref-xml';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getStatusNames()
	 */
	function getStatusNames() {
		return array_merge(parent::getStatusNames(), array(
			EXPORT_STATUS_REGISTERED => __('plugins.importexport.crossrefConference.status.registered'),
			CROSSREF_STATUS_FAILED => __('plugins.importexport.crossrefConference.status.failed'),
			EXPORT_STATUS_MARKEDREGISTERED => __('plugins.importexport.crossrefConference.status.markedRegistered'),
		));
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getStatusActions()
	 */
	function getStatusActions($pubObject) {
		$request = Application::get()->getRequest();
		$dispatcher = $request->getDispatcher();
		return array(
			CROSSREF_STATUS_FAILED =>
				new LinkAction(
					'failureMessage',
					new AjaxModal(
						$dispatcher->url(
							$request, ROUTE_COMPONENT, null,
							'grid.settings.plugins.settingsPluginGridHandler',
							'manage', null, array('plugin' => 'CrossrefConferenceExportPlugin', 'category' => 'importexport', 'verb' => 'statusMessage',
							'batchId' => $pubObject->getData($this->getDepositBatchIdSettingName()), 'articleId' => $pubObject->getId())
						),
						__('plugins.importexport.crossrefConference.status.failed'),
						'failureMessage'
					),
					__('plugins.importexport.crossrefConference.status.failed')
				)
		);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getStatusMessage()
	 */
	function getStatusMessage($request) {
		// if the failure occured on request and the message was saved
		// return that message
		$articleId = $request->getUserVar('articleId');
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$article = $submissionDao->getByid($articleId);
		$failedMsg = $article->getData($this->getFailedMsgSettingName());
		if (!empty($failedMsg)) {
			return $failedMsg;
		}

		$context = $request->getContext();

		$httpClient = Application::get()->getHttpClient();
		$response = $httpClient->request(
			'POST',
			$this->isTestMode($context) ? CROSSREF_API_STATUS_URL_DEV : CROSSREF_API_STATUS_URL,
			[
				'form_params' => [
					'doi_batch_id' => $request->getUserVar('batchId'),
					'type' => 'result',
					'usr' => $this->getSetting($context->getId(), 'username'),
					'pwd' => $this->getSetting($context->getId(), 'password'),
				]
			]
		);

		if ($response->getStatusCode() != 200) {
			return __('plugins.importexport.common.register.error.mdsError', array('param' => 'No response from server.'));
		}
		return (string) $response->getBody();
	}

	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
				$this->import('classes.form.CrossrefConferenceDataForm');
				$form = new CrossrefConferenceDataForm($this, $request->getContext()->getId());
				$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$notificationManager = new NotificationManager();
						$notificationManager->createTrivialNotification($request->getUser()->getId());
						return new JSONMessage(true);
					}
		}
		
		return parent::manage($args, $request);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActionNames()
	 */
	function getExportActionNames() {
		return array(
			EXPORT_ACTION_DEPOSIT => __('plugins.importexport.crossrefConference.action.register'),
			EXPORT_ACTION_EXPORT => __('plugins.importexport.crossrefConference.action.export'),
			EXPORT_ACTION_MARKREGISTERED => __('plugins.importexport.crossrefConference.action.markRegistered'),
		);
	}

	/**
	 * Get a list of additional setting names that should be stored with the objects.
	 * @return array
	 */
	protected function _getObjectAdditionalSettings() {
		return array_merge(parent::_getObjectAdditionalSettings(), array(
			$this->getDepositBatchIdSettingName(),
			$this->getFailedMsgSettingName(),
		));
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'crossrefConference';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSettingsFormClassName()
	 */
	function getSettingsFormClassName() {
		return 'CrossrefConferenceSettingsForm';
	}

	function getConfereceDataFormClassName() {
		return 'CrossrefConferenceDataForm';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportDeploymentClassName()
	 */
	function getExportDeploymentClassName() {
		return 'CrossrefConferenceExportDeployment';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::executeExportAction()
	 */
	function executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation = null) {
		$context = $request->getContext();
		$path = array('plugin', $this->getName());

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$resultErrors = array();

		if ($request->getUserVar(EXPORT_ACTION_DEPOSIT)) {
			assert($filter != null);
			// Errors occured will be accessible via the status link
			// thus do not display all errors notifications (for every article),
			// just one general.
			// Warnings occured when the registration was successfull will however be
			// displayed for each article.
			$errorsOccured = false;
			// The new Crossref deposit API expects one request per object.
			// On contrary the export supports bulk/batch object export, thus
			// also the filter expects an array of objects.
			// Thus the foreach loop, but every object will be in an one item array for
			// the export and filter to work.
			foreach ($objects as $object) {
				// Get the XML
				$exportXml = $this->exportXML(array($object), $filter, $context, $noValidation);
				// Write the XML to a file.
				// export file name example: crossref-20160723-160036-articles-1-1.xml
				$objectsFileNamePart = $objectsFileNamePart . '-' . $object->getId();
				$exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePart, $context, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				// Deposit the XML file.
				$result = $this->depositXML($object, $context, $exportFileName);
				if (!$result) {
					$errorsOccured = true;
				}
				if (is_array($result)) {
					$resultErrors[] = $result;
				}
				// Remove all temporary files.
				$fileManager->deleteByPath($exportFileName);
			}
			// send notifications
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
			// redirect back to the right tab
			$request->redirect(null, null, null, $path, null, $tab);
		} else {
			parent::executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation);
		}
	}

	/**
	 * @see PubObjectsExportPlugin::depositXML()
	 *
	 * @param $objects Submission
	 * @param $context Context
	 * @param $filename string Export XML filename
	 */
	function depositXML($objects, $context, $filename) {
		$status = null;
		$msg = null;

		$httpClient = Application::get()->getHttpClient();
		assert(is_readable($filename));
		try {
			$response = $httpClient->request(
				'POST',
				$this->isTestMode($context) ? CROSSREF_API_URL_DEV : CROSSREF_API_URL,
				[
					'form_params' => [
						'operation' => 'doMDUpload',
						'usr' => $this->getSetting($context->getId(), 'username'),
						'pwd' => $this->getSetting($context->getId(), 'password'),
						'mdFile' => fopen($filename, 'r'),
					]
				]
			);
		} catch (GuzzleHttp\Exception\RequestException $e) {
			return [['plugins.importexport.common.register.error.mdsError', 'No response from server.']];
		}
		if ($response->getStatusCode() != CROSSREF_API_DEPOSIT_OK) {
			// These are the failures that occur immediately on request
			// and can not be accessed later, so we save the falure message in the DB
			$xmlDoc = new DOMDocument();
			$xmlDoc->loadXML($response->getBody());
			// Get batch ID
			$batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
			// Get re message
			$msg = $response;
			$status = CROSSREF_STATUS_FAILED;
			$result = false;
		} else {
			// Get DOMDocument from the response XML string
			$xmlDoc = new DOMDocument();
			$xmlDoc->loadXML($response->getBody());
			$batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);

			// Get the DOI deposit status
			// If the deposit failed
			$failureCountNode = $xmlDoc->getElementsByTagName('failure_count')->item(0);
			$failureCount = (int) $failureCountNode->nodeValue;
			if ($failureCount > 0) {
				$status = CROSSREF_STATUS_FAILED;
				$result = false;
			} else {
				// Deposit was received
				$status = EXPORT_STATUS_REGISTERED;
				$result = true;

				// If there were some warnings, display them
				$warningCountNode = $xmlDoc->getElementsByTagName('warning_count')->item(0);
				$warningCount = (int) $warningCountNode->nodeValue;
				if ($warningCount > 0) {
					$result = array(array('plugins.importexport.crossrefConference.register.success.warning', htmlspecialchars($response->getBody())));
				}
				// A possibility for other plugins (e.g. reference linking) to work with the response
				HookRegistry::call('crossrefconferenceexportplugin::deposited', array($this, $response->getBody(), $objects));
			}
		}
		// Update the status
		if ($status) {
			$this->updateDepositStatus($context, $objects, $status, $batchIdNode->nodeValue, $msg);
			$this->updateObject($objects);
		}

		return $result;
	}

	/**
	 * Check the CrossRef APIs, if deposits and registration have been successful
	 * @param $context Context
	 * @param $object The object getting deposited
	 * @param $status CROSSREF_STATUS_...
	 * @param $batchId string
	 * @param $failedMsg string (opitonal)
	 */
	function updateDepositStatus($context, $object, $status, $batchId, $failedMsg = null) {
		assert(is_a($object, 'Submission') or is_a($object, 'Issue'));
		// remove the old failure message, if exists
		$object->setData($this->getFailedMsgSettingName(), null);
		$object->setData($this->getDepositStatusSettingName(), $status);
		$object->setData($this->getDepositBatchIdSettingName(), $batchId);
		if ($failedMsg) {
			$object->setData($this->getFailedMsgSettingName(), $failedMsg);
		}
		if ($status == EXPORT_STATUS_REGISTERED) {
			// Save the DOI -- the object will be updated
			$this->saveRegisteredDoi($context, $object);
		}
	}

	/**
	 * @copydoc DOIPubIdExportPlugin::markRegistered()
	 */
	function markRegistered($context, $objects) {
		foreach ($objects as $object) {
			// remove the old failure message, if exists
			$object->setData($this->getFailedMsgSettingName(), null);
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
			$this->saveRegisteredDoi($context, $object);
		}
	}

	/**
	 * Get request failed message setting name.
	 * @return string
	 */
	function getFailedMsgSettingName() {
		return $this->getPluginSettingsPrefix().'::failedMsg';
	}

	/**
	 * Get deposit batch ID setting name.
	 * @return string
	 */
	function getDepositBatchIdSettingName() {
		return $this->getPluginSettingsPrefix().'::batchId';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getDepositSuccessNotificationMessageKey()
	 */
	function getDepositSuccessNotificationMessageKey() {
		return 'plugins.importexport.common.register.success';
	}

	/**
	 * @copydoc PKPImportExportPlugin::executeCLI()
	 */
	function executeCLICommand($scriptName, $command, $context, $outputFile, $objects, $filter, $objectsFileNamePart) {
		switch ($command) {
			case 'export':
				PluginRegistry::loadCategory('generic', true, $context->getId());
				$exportXml = $this->exportXML($objects, $filter, $context);
				if ($outputFile) file_put_contents($outputFile, $exportXml);
				break;
			case 'register':
				PluginRegistry::loadCategory('generic', true, $context->getId());
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$resultErrors = array();
				// Errors occured will be accessible via the status link
				// thus do not display all errors notifications (for every article),
				// just one general.
				// Warnings occured when the registration was successfull will however be
				// displayed for each article.
				$errorsOccured = false;
				// The new Crossref deposit API expects one request per object.
				// On contrary the export supports bulk/batch object export, thus
				// also the filter expects an array of objects.
				// Thus the foreach loop, but every object will be in an one item array for
				// the export and filter to work.
				foreach ($objects as $object) {
					// Get the XML
					$exportXml = $this->exportXML(array($object), $filter, $context);
					// Write the XML to a file.
					// export file name example: crossref-20160723-160036-articles-1-1.xml
					$objectsFileNamePartId = $objectsFileNamePart . '-' . $object->getId();
					$exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePartId, $context, '.xml');
					$fileManager->writeFile($exportFileName, $exportXml);
					// Deposit the XML file.
					$result = $this->depositXML($object, $context, $exportFileName);
					if (!$result) {
						$errorsOccured = true;
					}
					if (is_array($result)) {
						$resultErrors[] = $result;
					}
					// Remove all temporary files.
					$fileManager->deleteByPath($exportFileName);
				}
				// display deposit result status messages
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


