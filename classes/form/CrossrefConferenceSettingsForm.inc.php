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
    //
    // Private properties
    //
    /** @var integer */
    public $_contextId;

    /**
     * Get the context ID.
     * @return integer
     */
    public function _getContextId()
    {
        return $this->_contextId;
    }

    /** @var CrossRefExportPlugin */
    public $_plugin;

    /**
     * Get the plugin.
     * @return CrossRefExportPlugin
     */
    public function _getPlugin()
    {
        return $this->_plugin;
    }


    //
    // Constructor
    //
    /**
     * Constructor
     * @param $plugin CrossRefExportPlugin
     * @param $contextId integer
     */
    public function __construct($plugin, $contextId)
    {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        // DOI plugin settings action link
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

        // Add form validation checks.
        $this->addCheck(new FormValidator($this, 'depositorName', 'required', 'plugins.importexport.crossrefConference.settings.form.depositorNameRequired'));
        $this->addCheck(new FormValidatorEmail($this, 'depositorEmail', 'required', 'plugins.importexport.crossrefConference.settings.form.depositorEmailRequired'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }


    //
    // Implement template methods from Form
    //
    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $contextId = $this->_getContextId();
        $plugin = $this->_getPlugin();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $plugin = $this->_getPlugin();
        $contextId = $this->_getContextId();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
        parent::execute(...$functionArgs);
    }


    //
    // Public helper methods
    //
    /**
     * Get form fields
     * @return array (field name => field type)
     */
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

    /**
     * Is the form field optional
     * @param $settingName string
     * @return boolean
     */
    public function isOptional($settingName)
    {
        return in_array($settingName, array('username', 'password', 'automaticRegistration', 'testMode'));
    }

}
