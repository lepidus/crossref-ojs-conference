<?php

/**
 * @file plugins/importexport/crossrefConference/CrossrefConferenceInfoSender.php
 *
 * @class CrossrefConferenceInfoSender
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Scheduled task to send deposits to CrossrefConference and update statuses.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');


class CrossrefConferenceInfoSender extends ScheduledTask
{
    public $_plugin;

    public function __construct($args)
    {
        PluginRegistry::loadCategory('importexport');
        $plugin = PluginRegistry::getPlugin('importexport', 'CrossrefConferenceExportPlugin');
        $this->_plugin = $plugin;

        if (is_a($plugin, 'CrossrefConferenceExportPlugin')) {
            $plugin->addLocaleData();
        }

        parent::__construct($args);
    }

    public function getName()
    {
        return __('plugins.importexport.crossrefConference.senderTask.name');
    }

    public function executeActions()
    {
        if (!$this->_plugin) {
            return false;
        }

        $plugin = $this->_plugin;
        $journals = $this->_getJournals();

        foreach ($journals as $journal) {
            $notify = false;

            $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $journal->getId());
            $doiPubIdPlugin = $pubIdPlugins['doipubidplugin'];

            if ($doiPubIdPlugin->getSetting($journal->getId(), 'enablePublicationDoi')) {
                $unregisteredArticles = $plugin->getUnregisteredArticles($journal);
                if (count($unregisteredArticles)) {
                    $this->_registerObjects($unregisteredArticles, 'paper=>crossref-xml', $journal, 'articles');
                }
            }
        }
        return true;
    }

    public function _getJournals()
    {
        $plugin = $this->_plugin;
        $contextDao = Application::getContextDAO();
        $journalFactory = $contextDao->getAll(true);

        $journals = array();
        while($journal = $journalFactory->next()) {
            $journalId = $journal->getId();
            if (!$plugin->getSetting($journalId, 'username') || !$plugin->getSetting($journalId, 'password') || !$plugin->getSetting($journalId, 'automaticRegistration')) {
                continue;
            }

            $doiPrefix = null;
            $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $journalId);
            if (isset($pubIdPlugins['doipubidplugin'])) {
                $doiPubIdPlugin = $pubIdPlugins['doipubidplugin'];
                if (!$doiPubIdPlugin->getSetting($journalId, 'enabled')) {
                    continue;
                }
                $doiPrefix = $doiPubIdPlugin->getSetting($journalId, 'doiPrefix');
            }

            if ($doiPrefix) {
                $journals[] = $journal;
            } else {
                $this->addExecutionLogEntry(__('plugins.importexport.common.senderTask.warning.noDOIprefix', array('path' => $journal->getPath())), SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
            }
        }
        return $journals;
    }

    public function _registerObjects($objects, $filter, $journal, $objectsFileNamePart)
    {
        $plugin = $this->_plugin;
        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        foreach ($objects as $object) {
            $exportXml = $plugin->exportXML(array($object), $filter, $journal);
            $objectsFileNamePartId = $objectsFileNamePart . '-' . $object->getId();
            $exportFileName = $plugin->getExportFileName($plugin->getExportPath(), $objectsFileNamePartId, $journal, '.xml');
            $fileManager->writeFile($exportFileName, $exportXml);
            $result = $plugin->depositXML($object, $journal, $exportFileName);
            if ($result !== true) {
                $this->_addLogEntry($result);
            }
            $fileManager->deleteByPath($exportFileName);
        }
    }

    public function _addLogEntry($result)
    {
        if (is_array($result)) {
            foreach($result as $error) {
                assert(is_array($error) && count($error) >= 1);
                $this->addExecutionLogEntry(
                    __($error[0], array('param' => (isset($error[1]) ? $error[1] : null))),
                    SCHEDULED_TASK_MESSAGE_TYPE_WARNING
                );
            }
        }
    }

}
