<?php
/**
 * @defgroup plugins_importexport_crossrefConference CrossrefConference export plugin
 */

/**
 * @file plugins/importexport/crossref/CrossrefConferenceExportDeployment.inc.php
 *
 * @class CrossrefConferenceExportDeployment
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Base class configuring the crossrefConference export process to an
 * application's specifics.
 */

define('CROSSREF_CONFERENCE_XMLNS', 'http://www.crossref.org/schema/4.3.6');
define('CROSSREF_CONFERENCE_XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('CROSSREF_CONFERENCE_XSI_SCHEMAVERSION', '4.3.6');
define('CROSSREF_CONFERENCE_XSI_SCHEMALOCATION', 'http://www.crossref.org/schema/crossref4.3.6.xsd');
define('CROSSREF_CONFERENCE_XSI_DEPOSIT', 'http://www.crossref.org/schema/deposit/crossref4.3.6.xsd');


class CrossrefConferenceExportDeployment
{
    public $_context;

    public $_plugin;

    public $_issue;

    public function getCache()
    {
        return $this->_plugin->getCache();
    }

    public function __construct($context, $plugin)
    {
        $this->setContext($context);
        $this->setPlugin($plugin);
    }

    public function getRootElementName()
    {
        return 'doi_batch';
    }

    public function getNamespace()
    {
        return CROSSREF_CONFERENCE_XMLNS;
    }

    public function getXmlSchemaInstance()
    {
        return CROSSREF_CONFERENCE_XMLNS_XSI;
    }

    public function getXmlSchemaVersion()
    {
        return CROSSREF_CONFERENCE_XSI_SCHEMAVERSION;
    }

    public function getXmlSchemaLocation()
    {
        return CROSSREF_CONFERENCE_XSI_SCHEMALOCATION;
    }

    public function getSchemaFilename()
    {
        return CROSSREF_CONFERENCE_XSI_DEPOSIT;
    }

    public function setContext($context)
    {
        $this->_context = $context;
    }

    public function getContext()
    {
        return $this->_context;
    }

    public function setPlugin($plugin)
    {
        $this->_plugin = $plugin;
    }

    public function getPlugin()
    {
        return $this->_plugin;
    }

    public function setIssue($issue)
    {
        $this->_issue = $issue;
    }

    public function getIssue()
    {
        return $this->_issue;
    }

}
