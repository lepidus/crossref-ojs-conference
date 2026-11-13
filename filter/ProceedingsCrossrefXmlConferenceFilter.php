<?php

/**
 * @class ProceedingsCrossrefXmlConferenceFilter
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Class that converts an Proceedings to a Crossref Conference XML document.
 */

namespace APP\plugins\generic\crossrefConference\filter;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\generic\crossrefConference\classes\CrossrefConferenceSettings;
use APP\submission\Submission;
use DOMDocument;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ProceedingsCrossrefXmlConferenceFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML proceedings export');
        parent::__construct($filterGroup);
    }

    public function &process(&$pubObjects)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $this->createRootNode($doc);
        $doc->appendChild($rootNode);

        $rootNode->appendChild($this->createHeadNode($doc));
        $bodyNode = $doc->createElementNS($deployment->getNamespace(), 'body');
        $rootNode->appendChild($bodyNode);

        foreach ($pubObjects as $pubObject) {
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

        $depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'depositor_name', htmlspecialchars((string) $depositorName, ENT_COMPAT, 'UTF-8')));
        $depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'email_address', htmlspecialchars((string) $depositorEmail, ENT_COMPAT, 'UTF-8')));

        $headNode->appendChild($depositorNode);
        $publisherInstitution = $context->getData('publisherInstitution');

        $headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'registrant', htmlspecialchars((string) $publisherInstitution, ENT_COMPAT, 'UTF-8')));
        return $headNode;
    }

    public function createConferenceNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $conferenceNode = $doc->createElementNS($deployment->getNamespace(), 'conference');
        $conferenceNode->appendChild($this->createEventMetadataNode($doc, $pubObject));
        $conferenceNode->appendChild($this->createProceedingsSeriesMetadataNode($doc, $pubObject));
        return $conferenceNode;
    }

    public function createEventMetadataNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $plugin = $deployment->getPlugin();

        if ($pubObject instanceof Issue) {
            $issue = $pubObject;
        } elseif ($pubObject instanceof Submission) {
            $issue = $this->getIssueOfSubmission($pubObject);
        } else {
            return;
        }

        $conferenceNameOption = $plugin->getSetting($context->getId(), 'conferenceNameOption');
        if ($conferenceNameOption === CrossrefConferenceSettings::CONFERENCE_NAME_FROM_ISSUE_TITLE && $issue->getLocalizedTitle() != '') {
            $conferenceName = $issue->getLocalizedTitle();
        } else {
            $conferenceName = $plugin->getSetting($context->getId(), 'conferenceName');
        }
        $eventMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'event_metadata');
        $eventMetadataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'conference_name', htmlspecialchars((string) $conferenceName, ENT_COMPAT, 'UTF-8')));

        $conferencePlaceCity = $issue->getData('conferencePlaceCity');
        $conferencePlaceCountry = $issue->getData('conferencePlaceCountry');
        $locationParts = array_filter([$conferencePlaceCity, $conferencePlaceCountry]);
        $conferenceLocation = implode(', ', $locationParts);
        if (!empty($conferenceLocation)) {
            $eventMetadataNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'conference_location',
                htmlspecialchars($conferenceLocation, ENT_COMPAT, 'UTF-8')
            ));
        }

        $conferenceDateBegin = $issue->getData('conferenceDateBegin');
        $conferenceDateEnd = $issue->getData('conferenceDateEnd');
        if (!empty($conferenceDateBegin) || !empty($conferenceDateEnd)) {
            $start_day = date('d', strtotime((string) $conferenceDateBegin));
            $start_month = date('m', strtotime((string) $conferenceDateBegin));
            $start_year = date('Y', strtotime((string) $conferenceDateBegin));
            $end_day = date('d', strtotime((string) $conferenceDateEnd));
            $end_month = date('m', strtotime((string) $conferenceDateEnd));
            $end_year = date('Y', strtotime((string) $conferenceDateEnd));
            $conferenceDateNode = $doc->createElementNS($deployment->getNamespace(), 'conference_date');
            $conferenceDateNode->setAttribute('start_month', $start_month);
            $conferenceDateNode->setAttribute('start_year', $start_year);
            $conferenceDateNode->setAttribute('start_day', $start_day);
            $conferenceDateNode->setAttribute('end_month', $end_month);
            $conferenceDateNode->setAttribute('end_year', $end_year);
            $conferenceDateNode->setAttribute('end_day', $end_day);
            $eventMetadataNode->appendChild($conferenceDateNode);
        }

        return $eventMetadataNode;
    }

    public function createProceedingsSeriesMetadataNode($doc, $issue)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $proceedingsSeriesMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'proceedings_series_metadata');
        $seriesMetadata = $doc->createElementNS($deployment->getNamespace(), 'series_metadata');
        $proceedingsTitle = $context->getName($context->getPrimaryLocale());
        if ($proceedingsTitle == '') {
            $proceedingsTitle = $context->getData('abbreviation', $context->getPrimaryLocale());
        }
        $titles = $seriesMetadata->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'titles'));
        $titles->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars((string) $proceedingsTitle, ENT_COMPAT, 'UTF-8')));
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
        $publisher->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'publisher_name', (string) $publisherName));
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
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi', htmlspecialchars((string) $doi, ENT_COMPAT, 'UTF-8')));
        $doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $url));
        return $doiDataNode;
    }

    protected function getIssueOfSubmission(Submission $submission): ?Issue
    {
        $deployment = $this->getDeployment();
        $cache = $deployment->getCache();
        $issueId = $submission->getCurrentPublication()->getData('issueId');
        if ($cache->isCached('issues', $issueId)) {
            return $cache->get('issues', $issueId);
        }

        $issue = $issueId ? Repo::issue()->get($issueId, $deployment->getContext()->getId()) : null;
        if ($issue) {
            $cache->add($issue, null);
        }
        return $issue;
    }
}
