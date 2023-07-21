<?php

/**
 * @file plugins/importexport/crossrefConference/filter/ProceedingsCrossrefXmlConferenceFilter.inc.php
 *
 * @class ProceedingsCrossrefXmlConferenceFilter
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Class that converts an Proceedings to a Crossref Conference XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ProceedingsCrossrefXmlConferenceFilter extends NativeExportFilter
{
    /**
     * Constructor
     * @param $filterGroup FilterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML proceedings export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from PersistableFilter
    //
    /**
     * @copydoc PersistableFilter::getClassName()
     */
    public function getClassName()
    {
        return 'plugins.importexport.crossrefConference.filter.ProceedingsCrossrefConferenceXmlFilter';
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     * @param $pubObjects array Array of Issues or Submissions
     * @return DOMDocument
     */

    public function &process(&$pubObjects)
    {
        // Create the XML document
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the root node
        $rootNode = $this->createRootNode($doc);
        $doc->appendChild($rootNode);

        // Create and appet the 'head' node and all parts inside it
        $rootNode->appendChild($this->createHeadNode($doc));

        // Create and append the 'body' node, that contains everything
        $bodyNode = $doc->createElementNS($deployment->getNamespace(), 'body');
        $rootNode->appendChild($bodyNode);

        foreach($pubObjects as $pubObject) {
            // pubObject is either Issue or Submission
            $conferenceNode = $this->createConferenceNode($doc, $pubObject);
            $bodyNode->appendChild($conferenceNode);
        }
        return $doc;
    }

    //
    // Issue conversion functions
    //
    /**
     * Create and return the root node 'doi_batch'.
     * @param $doc DOMDocument
     * @return DOMElement
     */

    public function createRootNode($doc)
    {
        $deployment = $this->getDeployment();
        $rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
        $rootNode->setAttribute('version', $deployment->getXmlSchemaVersion());
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
        return $rootNode;
    }

    /**
     * Create and return the head node 'head'.
     * @param $doc DOMDocument
     * @return DOMElement
     */

    public function createHeadNode($doc)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $plugin = $deployment->getPlugin();
        $headNode = $doc->createElementNS($deployment->getNamespace(), 'head');
        $headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi_batch_id', htmlspecialchars($context->getData('initials', $context->getPrimaryLocale()) . 'proceeding' . '_' . time(), ENT_COMPAT, 'UTF-8')));
        $headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'timestamp', date('YmdHisv')));
        $depositorNode = $doc->createElementNS($deployment->getNamespace(), 'depositor');

        $depositorName = $plugin->getSetting($context->getId(), 'depositorName');
        if (empty($depositorName)) {
            $depositorName = $context->getData('supportName');
        }

        $depositorEmail = $plugin->getSetting($context->getId(), 'depositorEmail');
        if (empty($depositorEmail)) {
            $depositorEmail = $context->getData('supportEmail');
        }

        $depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'depositor_name', htmlspecialchars($depositorName, ENT_COMPAT, 'UTF-8')));
        $depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'email_address', htmlspecialchars($depositorEmail, ENT_COMPAT, 'UTF-8')));

        $headNode->appendChild($depositorNode);
        $publisherInstitution = $context->getData('publisherInstitution');

        $headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'registrant', htmlspecialchars($publisherInstitution, ENT_COMPAT, 'UTF-8')));
        return $headNode;
    }

    /**
     * Create and return the conference node 'conference'.
     * @param $doc DOMDocument
     * @param $pubObject object Issue or Submission
     * @return DOMElement
     */

    public function createConferenceNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $conferenceNode = $doc->createElementNS($deployment->getNamespace(), 'conference');
        $conferenceNode->appendChild($this->createEventMetadataNode($doc));
        $conferenceNode->appendChild($this->createProceedingsSeriesMetadataNode($doc, $pubObject));
        return $conferenceNode;
    }

    /**
     * Create and return the event metadata node 'event_metadata'.
     * @param $doc DOMDocument
     * @return DOMElement
     */

    public function createEventMetadataNode($doc)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $plugin = $deployment->getPlugin();

        $conferenceName = $plugin->getSetting($context->getId(), 'conferenceName');
        $eventMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'event_metadata');
        $eventMetadataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'conference_name', htmlspecialchars($conferenceName, ENT_COMPAT, 'UTF-8')));

        return $eventMetadataNode;
    }

    /**
     * Create and return the proceedings series metadata node 'proceedings_series_metadata'.
     * @param $doc DOMDocument
     * @param $issue Issue
     * @return DOMElement
     */

    public function createProceedingsSeriesMetadataNode($doc, $issue)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $journalIssueNode = $doc->createElementNS($deployment->getNamespace(), 'journal_issue');

        $proceedingsSeriesMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'proceedings_series_metadata');
        $seriesMetadata = $doc->createElementNS($deployment->getNamespace(), 'series_metadata');
        $proceedingsTitle = $context->getName($context->getPrimaryLocale());
        // Attempt a fall back, in case the localized name is not set.
        if ($proceedingsTitle == '') {
            $proceedingsTitle = $context->getData('abbreviation', $context->getPrimaryLocale());
        }
        $titles = $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'titles'));
        $titles->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($proceedingsTitle, ENT_COMPAT, 'UTF-8')));
        /* Both ISSNs are permitted for CrossRef, so sending whichever one (or both)*/
        if ($ISSN = $context->getData('onlineIssn')) {
            $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'issn', $ISSN));
            $node->setAttribute('media_type', 'electronic');
        }
        /*Both ISSNs are permitted for CrossRef so sending whichever one (or both)*/
        if ($ISSN = $context->getData('printIssn')) {
            $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'issn', $ISSN));
            $node->setAttribute('media_type', 'print');
        }

        $proceedingsSeriesMetadataNode->appendChild($seriesMetadata);

        $publisher = $doc->createElementNS($deployment->getNamespace(), 'publisher');
        $publisherName = $context->getData('publisherInstitution');
        $publisher->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'publisher_name', $publisherName));
        $proceedingsSeriesMetadataNode->appendChild($publisher);
        if ($issue->getDatePublished()) {
            $proceedingsSeriesMetadataNode->appendChild($this->createPublicationDateNode($doc, $issue->getDatePublished()));
        }

        return $proceedingsSeriesMetadataNode;
    }

    /**
     * Create and return the publication date node 'publication_date'.
     * @param $doc DOMDocument
     * @param $objectPublicationDate string
     * @return DOMElement
     */

    public function createPublicationDateNode($doc, $objectPublicationDate)
    {
        $deployment = $this->getDeployment();
        $publicationDate = strtotime($objectPublicationDate);
        $publicationDateNode = $doc->createElementNS($deployment->getNamespace(), 'publication_date');
        $publicationDateNode->setAttribute('media_type', 'online');
        if (date('m', $publicationDate)) {
            $publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'month', date('m', $publicationDate)));
        }
        if (date('d', $publicationDate)) {
            $publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'day', date('d', $publicationDate)));
        }
        $publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'year', date('Y', $publicationDate)));
        return $publicationDateNode;
    }

    /**
     * Create and return the DOI date node 'doi_data'.
     * @param $doc DOMDocument
     * @param $doi string
     * @param $url string
     * @return DOMElement
     */
    public function createDOIDataNode($doc, $doi, $url)
    {
        $deployment = $this->getDeployment();
        $doiDataNode = $doc->createElementNS($deployment->getNamespace(), 'doi_data');
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi', htmlspecialchars($doi, ENT_COMPAT, 'UTF-8')));
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $url));
        return $doiDataNode;
    }

}
