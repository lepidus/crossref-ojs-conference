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

// XML attributes
define('CROSSREF_CONFERENCE_XMLNS', 'http://www.crossref.org/schema/4.3.6');
define('CROSSREF_CONFERENCE_XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('CROSSREF_CONFERENCE_XSI_SCHEMAVERSION', '4.3.6');
define('CROSSREF_CONFERENCE_XSI_SCHEMALOCATION', 'http://www.crossref.org/schema/crossref4.3.6.xsd');
define('CROSSREF_CONFERENCE_XMLNS_JATS', 'http://www.ncbi.nlm.nih.gov/JATS1');
define('CROSSREF_CONFERENCE_XMLNS_AI', 'http://www.crossref.org/AccessIndicators.xsd');
define('CROSSREF_CONFERENCE_XSI_DEPOSIT', 'http://www.crossref.org/schema/deposit/crossref4.3.6.xsd');


class CrossrefConferenceExportDeployment
{
    /** @var Context The current import/export context */
    public $_context;

    /** @var Plugin The current import/export plugin */
    public $_plugin;

    /** @var Issue */
    public $_issue;

    public function getCache()
    {
        return $this->_plugin->getCache();
    }

    /**
     * Constructor
     * @param $context Context
     * @param $plugin DOIPubIdExportPlugin
     */
    public function __construct($context, $plugin)
    {
        $this->setContext($context);
        $this->setPlugin($plugin);
    }

    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the root lement name
     * @return string
     */
    public function getRootElementName()
    {
        return 'doi_batch';
    }

    /**
     * Get the namespace URN
     * @return string
     */
    public function getNamespace()
    {
        return CROSSREF_CONFERENCE_XMLNS;
    }

    /**
     * Get the schema instance URN
     * @return string
     */
    public function getXmlSchemaInstance()
    {
        return CROSSREF_CONFERENCE_XMLNS_XSI;
    }

    /**
     * Get the schema version
     * @return string
     */
    public function getXmlSchemaVersion()
    {
        return CROSSREF_CONFERENCE_XSI_SCHEMAVERSION;
    }

    /**
     * Get the schema location URL
     * @return string
     */
    public function getXmlSchemaLocation()
    {
        return CROSSREF_CONFERENCE_XSI_SCHEMALOCATION;
    }

    /**
     * Get the JATS namespace URN
     * @return string
     */
    public function getJATSNamespace()
    {
        return CROSSREF_CONFERENCE_XMLNS_JATS;
    }

    /**
     * Get the access indicators namespace URN
     * @return string
     */
    public function getAINamespace()
    {
        return CROSSREF_CONFERENCE_XMLNS_AI;
    }

    /**
     * Get the schema filename.
     * @return string
     */
    public function getSchemaFilename()
    {
        return CROSSREF_CONFERENCE_XSI_DEPOSIT;
    }

    //
    // Getter/setters
    //
    /**
     * Set the import/export context.
     * @param $context Context
     */
    public function setContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Get the import/export context.
     * @return Context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Set the import/export plugin.
     * @param $plugin ImportExportPlugin
     */
    public function setPlugin($plugin)
    {
        $this->_plugin = $plugin;
    }

    /**
     * Get the import/export plugin.
     * @return ImportExportPlugin
     */
    public function getPlugin()
    {
        return $this->_plugin;
    }

    /**
     * Set the import/export issue.
     * @param $issue Issue
     */
    public function setIssue($issue)
    {
        $this->_issue = $issue;
    }

    /**
     * Get the import/export issue.
     * @return Issue
     */
    public function getIssue()
    {
        return $this->_issue;
    }

}
