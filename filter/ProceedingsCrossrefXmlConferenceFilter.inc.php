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
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML proceedings export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.crossrefConference.filter.ProceedingsCrossrefConferenceXmlFilter';
    }

    public function &process(&$pubObjects)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $rootNode = $this->createRootNode($doc);
        $doc->appendChild($rootNode);

        $rootNode->appendChild($this->createHeadNode($doc));
        $bodyNode = $doc->createElementNS($deployment->getNamespace(), 'body');
        $rootNode->appendChild($bodyNode);

        foreach($pubObjects as $pubObject) {
            $conferenceNode = $this->createConferenceNode($doc, $pubObject);
            $bodyNode->appendChild($conferenceNode);
        }
        return $doc;
    }

    public function createRootNode($doc)
    {
        $deployment = $this->getDeployment();
        $rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
        $rootNode->setAttribute('version', $deployment->getXmlSchemaVersion());
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
        return $rootNode;
    }

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

    public function createConferenceNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $conferenceNode = $doc->createElementNS($deployment->getNamespace(), 'conference');
        $conferenceNode->appendChild($this->createEventMetadataNode($doc));
        $conferenceNode->appendChild($this->createProceedingsSeriesMetadataNode($doc, $pubObject));
        return $conferenceNode;
    }

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

    public function createProceedingsSeriesMetadataNode($doc, $issue)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $journalIssueNode = $doc->createElementNS($deployment->getNamespace(), 'journal_issue');

        $proceedingsSeriesMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'proceedings_series_metadata');
        $seriesMetadata = $doc->createElementNS($deployment->getNamespace(), 'series_metadata');
        $proceedingsTitle = $context->getName($context->getPrimaryLocale());
        if ($proceedingsTitle == '') {
            $proceedingsTitle = $context->getData('abbreviation', $context->getPrimaryLocale());
        }
        $titles = $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'titles'));
        $titles->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($proceedingsTitle, ENT_COMPAT, 'UTF-8')));
        if ($ISSN = $context->getData('onlineIssn')) {
            $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'issn', $ISSN));
            $node->setAttribute('media_type', 'electronic');
        }
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

    public function createDOIDataNode($doc, $doi, $url)
    {
        $deployment = $this->getDeployment();
        $doiDataNode = $doc->createElementNS($deployment->getNamespace(), 'doi_data');
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi', htmlspecialchars($doi, ENT_COMPAT, 'UTF-8')));
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $url));
        return $doiDataNode;
    }
}
