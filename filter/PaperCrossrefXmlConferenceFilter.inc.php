<?php

/**
 * @file plugins/importexport/crossref/filter/PaperCrossrefXmlConferenceFilter.inc.php
 *
 * @class PaperCrossrefXmlConferenceFilter
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Class that converts an Paper to a Crossref Conference XML document.
 */

import('plugins.importexport.crossrefConference.filter.ProceedingsCrossrefXmlConferenceFilter');

class PaperCrossrefXmlConferenceFilter extends ProceedingsCrossrefXmlConferenceFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML paper export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter';
    }

    public function createConferenceNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $conferenceNode = parent::createConferenceNode($doc, $pubObject);
        assert(is_a($pubObject, 'Submission'));
        $conferenceNode->appendChild($this->createConferencePaperNode($doc, $pubObject));
        return $conferenceNode;
    }

    public function createProceedingsSeriesMetadataNode($doc, $submission)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $cache = $deployment->getCache();
        assert(is_a($submission, 'Submission'));
        $issueId = $submission->getCurrentPublication()->getData('issueId');
        if ($cache->isCached('issues', $issueId)) {
            $issue = $cache->get('issues', $issueId);
        } else {
            $issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
            $issue = $issueDao->getById($issueId, $context->getId());
            if ($issue) {
                $cache->add($issue, null);
            }
        }
        $proceedingsSeriesMetadataNode = parent::createProceedingsSeriesMetadataNode($doc, $issue);
        return $proceedingsSeriesMetadataNode;
    }

    public function createConferencePaperNode($doc, $submission)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $request = Application::get()->getRequest();

        $publication = $submission->getCurrentPublication();

        $locale = $publication->getData('locale');

        $issue = $deployment->getIssue();

        $conferencePaperNode = $doc->createElementNS($deployment->getNamespace(), 'conference_paper');
        $conferencePaperNode->setAttribute('publication_type', 'full_text');
        $conferencePaperNode->setAttribute('metadata_distribution_opts', 'any');

        $titlesNode = $doc->createElementNS($deployment->getNamespace(), 'titles');
        $titlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($publication->getData('title', $locale), ENT_COMPAT, 'UTF-8')));
        if ($subtitle = $publication->getData('subtitle', $locale)) {
            $titlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'subtitle', htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8')));
        }
        $conferencePaperNode->appendChild($titlesNode);

        $authors = $publication->getData('authors');

        if(!empty($author)) {
            $contributorsNode = $doc->createElementNS($deployment->getNamespace(), 'contributors');
            $isFirst = true;

            foreach ($authors as $author) {
                $personNameNode = $doc->createElementNS($deployment->getNamespace(), 'person_name');
                $personNameNode->setAttribute('contributor_role', 'author');

                if ($isFirst) {
                    $personNameNode->setAttribute('sequence', 'first');
                } else {
                    $personNameNode->setAttribute('sequence', 'additional');
                }

                $familyNames = $author->getFamilyName(null);
                $givenNames = $author->getGivenName(null);

                if (!empty($familyNames[$locale]) && !empty($givenNames[$locale])) {
                    $personNameNode->setAttribute('language', PKPLocale::getIso1FromLocale($locale));
                    $personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'given_name', htmlspecialchars(ucfirst($givenNames[$locale]), ENT_COMPAT, 'UTF-8')));
                    $personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'surname', htmlspecialchars(ucfirst($familyNames[$locale]), ENT_COMPAT, 'UTF-8')));
                    $hasAltName = false;

                    if ($author->getData('orcid')) {
                        $personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'ORCID', $author->getData('orcid')));
                    }

                    foreach($familyNames as $otherLocal => $familyName) {
                        if ($otherLocal != $locale && isset($familyName) && !empty($familyName)) {
                            if (!$hasAltName) {
                                $altNameNode = $doc->createElementNS($deployment->getNamespace(), 'alt-name');
                                $personNameNode->appendChild($altNameNode);

                                $hasAltName = true;
                            }

                            $nameNode = $doc->createElementNS($deployment->getNamespace(), 'name');
                            $nameNode->setAttribute('language', PKPLocale::getIso1FromLocale($otherLocal));

                            $nameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'surname', htmlspecialchars(ucfirst($familyName), ENT_COMPAT, 'UTF-8')));
                            if (isset($givenNames[$otherLocal]) && !empty($givenNames[$otherLocal])) {
                                $nameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'given_name', htmlspecialchars(ucfirst($givenNames[$otherLocal]), ENT_COMPAT, 'UTF-8')));
                            }

                            $altNameNode->appendChild($nameNode);
                        }
                    }

                } else {
                    $personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'surname', htmlspecialchars(ucfirst($givenNames[$locale]), ENT_COMPAT, 'UTF-8')));
                }

                $contributorsNode->appendChild($personNameNode);
                $isFirst = false;
            }
            $conferencePaperNode->appendChild($contributorsNode);
        }

        if ($datePublished = $publication->getData('datePublished')) {
            $conferencePaperNode->appendChild($this->createPublicationDateNode($doc, $datePublished));
        }

        $doiDataNode = $this->createDOIDataNode($doc, $publication->getStoredPubId('doi'), $request->url($context->getPath(), 'article', 'view', $submission->getBestId(), null, null, true));

        $galleys = $publication->getData('galleys');

        $submissionGalleys = $pdfGalleys = $remoteGalleys = array();

        $pdfGalleyInArticleLocale = null;

        $componentGalleys = array();
        $genreDao = DAORegistry::getDAO('GenreDAO');

        foreach ($galleys as $galley) {
            if (!$galley->getRemoteURL()) {
                $galleyFile = $galley->getFile();
                if ($galleyFile) {
                    $genre = $genreDao->getById($galleyFile->getGenreId());
                    if ($genre->getSupplementary()) {
                        if ($galley->getStoredPubid('doi')) {
                            $componentGalleys[] = $galley;
                        }
                    } else {
                        $submissionGalleys[] = $galley;
                        if ($galley->isPdfGalley()) {
                            $pdfGalleys[] = $galley;
                            if (!$pdfGalleyInArticleLocale && $galley->getLocale() == $locale) {
                                $pdfGalleyInArticleLocale = $galley;
                            }
                        }
                    }
                }
            } else {
                $remoteGalleys[] = $galley;
            }
        }

        $asCrawledGalleys = array();
        if ($pdfGalleyInArticleLocale) {
            $asCrawledGalleys = array($pdfGalleyInArticleLocale);
        } elseif (!empty($pdfGalleys)) {
            $asCrawledGalleys = array($pdfGalleys[0]);
        } else {
            $asCrawledGalleys = $submissionGalleys;
        }

        $this->appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $asCrawledGalleys);

        $submissionGalleys = array_merge($submissionGalleys, $remoteGalleys);
        $this->appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $submissionGalleys);
        $conferencePaperNode->appendChild($doiDataNode);

        return $conferencePaperNode;

    }

    public function appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $galleys)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $request = Application::get()->getRequest();

        if (empty($galleys)) {
            $crawlerBasedCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
            $crawlerBasedCollectionNode->setAttribute('property', 'crawler-based');
            $doiDataNode->appendChild($crawlerBasedCollectionNode);
        }
        foreach ($galleys as $galley) {
            $resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $galley->getBestGalleyId()), null, null, true);
            $crawlerBasedCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
            $crawlerBasedCollectionNode->setAttribute('property', 'crawler-based');
            $iParadigmsItemNode = $doc->createElementNS($deployment->getNamespace(), 'item');
            $iParadigmsItemNode->setAttribute('crawler', 'iParadigms');
            $iParadigmsItemNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $resourceURL));
            $crawlerBasedCollectionNode->appendChild($iParadigmsItemNode);
            $doiDataNode->appendChild($crawlerBasedCollectionNode);
        }
    }

    public function appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $galleys)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $request = Application::get()->getRequest();

        $textMiningCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
        $textMiningCollectionNode->setAttribute('property', 'text-mining');
        foreach ($galleys as $galley) {
            $resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $galley->getBestGalleyId()), null, null, true);
            $textMiningItemNode = $doc->createElementNS($deployment->getNamespace(), 'item');
            $resourceNode = $doc->createElementNS($deployment->getNamespace(), 'resource', $resourceURL);
            if (!$galley->getRemoteURL()) {
                $resourceNode->setAttribute('mime_type', $galley->getFileType());
            }
            $textMiningItemNode->appendChild($resourceNode);
            $textMiningCollectionNode->appendChild($textMiningItemNode);
        }
        $doiDataNode->appendChild($textMiningCollectionNode);
    }
}
