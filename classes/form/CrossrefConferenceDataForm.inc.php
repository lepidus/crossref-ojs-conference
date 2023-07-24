<?php

/**
 * @file plugins/importexport/crossrefConference/classes/form/CrossrefConferenceDataForm.inc.php
 *
 * @class CrossrefConferenceDataForm
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Form for conference managers to setup CrossrefConference plugin
 */


import('lib.pkp.classes.form.Form');

class CrossrefConferenceDataForm extends Form
{
    public $_contextId;

    public function _getContextId()
    {
        return $this->_contextId;
    }

    public $_plugin;

    public function _getPlugin()
    {
        return $this->_plugin;
    }

    public function __construct($plugin, $contextId)
    {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('conferenceDataForm.tpl'));

        $this->addCheck(new FormValidator($this, 'conferenceName', 'required', 'plugins.importexport.crossrefConference.settings.form.conferenceNameRequired'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $contextId = $this->_getContextId();
        $plugin = $this->_getPlugin();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
        }
    }

    public function readInputData()
    {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    public function execute(...$functionArgs)
    {
        $plugin = $this->_getPlugin();
        $contextId = $this->_getContextId();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
        parent::execute(...$functionArgs);
    }

    public function getFormFields()
    {
        return array(
            'conferenceName' => 'string'
        );
    }
}
