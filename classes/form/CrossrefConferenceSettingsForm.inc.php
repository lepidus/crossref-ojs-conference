<?php

/**
 * @file plugins/importexport/crossrefConference/classes/form/CrossrefConferenceSettingsForm.inc.php
 *
 * @class CrossrefConferenceSettingsForm
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Form for conference managers to setup CrossrefConference plugin
 */


import('lib.pkp.classes.form.Form');

class CrossrefConferenceSettingsForm extends Form
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

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
        if (isset($pubIdPlugins['doipubidplugin'])) {
            $application = Application::get();
            $request = $application->getRequest();
            $dispatcher = $application->getDispatcher();
            import('lib.pkp.classes.linkAction.request.AjaxModal');
            $doiPluginSettingsLinkAction = new LinkAction(
                'settings',
                new AjaxModal(
                    $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'manage', null, array('plugin' => 'doipubidplugin', 'category' => 'pubIds')),
                    __('plugins.importexport.common.settings.DOIPluginSettings')
                ),
                __('plugins.importexport.common.settings.DOIPluginSettings'),
                null
            );
            $this->setData('doiPluginSettingsLinkAction', $doiPluginSettingsLinkAction);
        }

        $this->addCheck(new FormValidator($this, 'depositorName', 'required', 'plugins.importexport.crossrefConference.settings.form.depositorNameRequired'));
        $this->addCheck(new FormValidatorEmail($this, 'depositorEmail', 'required', 'plugins.importexport.crossrefConference.settings.form.depositorEmailRequired'));
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
            'depositorName' => 'string',
            'depositorEmail' => 'string',
            'username' => 'string',
            'password' => 'string',
            'automaticRegistration' => 'bool',
            'testMode' => 'bool'
        );
    }

    public function isOptional($settingName)
    {
        return in_array($settingName, array('username', 'password', 'automaticRegistration', 'testMode'));
    }

}
