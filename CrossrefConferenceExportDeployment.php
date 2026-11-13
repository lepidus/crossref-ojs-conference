<?php

namespace APP\plugins\generic\crossrefConference;

use PKP\context\Context;
use PKP\plugins\Plugin;

class CrossrefConferenceExportDeployment
{
    public const CROSSREF_CONFERENCE_XMLNS = 'http://www.crossref.org/schema/4.3.6';
    public const CROSSREF_CONFERENCE_XMLNS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    public const CROSSREF_CONFERENCE_XSI_SCHEMAVERSION = '4.3.6';
    public const CROSSREF_CONFERENCE_XSI_DEPOSIT = 'http://www.crossref.org/schema/deposit/crossref4.3.6.xsd';

    public $_context;

    public $_plugin;

    public function __construct(Context $context, Plugin $plugin)
    {
        $this->setContext($context);
        $this->setPlugin($plugin);
    }

    public function getCache()
    {
        return $this->_plugin->getCache();
    }

    public function getRootElementName()
    {
        return 'doi_batch';
    }

    public function getNamespace()
    {
        return static::CROSSREF_CONFERENCE_XMLNS;
    }

    public function getXmlSchemaInstance()
    {
        return static::CROSSREF_CONFERENCE_XMLNS_XSI;
    }

    public function getXmlSchemaVersion()
    {
        return static::CROSSREF_CONFERENCE_XSI_SCHEMAVERSION;
    }

    public function getSchemaFilename()
    {
        return static::CROSSREF_CONFERENCE_XSI_DEPOSIT;
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
}
