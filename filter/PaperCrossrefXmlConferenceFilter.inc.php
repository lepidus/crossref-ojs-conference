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
    /**
     * Constructor
     * @param $filterGroup FilterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML paper export');
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
        return 'plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter';
    }


    //
    // Submission conversion functions
    //
    /**
     * @copydoc ProceedingsCrossrefXmlConferenceFilter::createConferenceNode()
     */
    public function createConferenceNode($doc, $pubObject)
    {
        $deployment = $this->getDeployment();
        $conferenceNode = parent::createConferenceNode($doc, $pubObject);
        assert(is_a($pubObject, 'Submission'));
        $conferenceNode->appendChild($this->createConferencePaperNode($doc, $pubObject));
        return $conferenceNode;
    }

    /**
     * Create and return the proceedings series metadata node 'proceedings_series_metadata'.
     * @param $doc DOMDocument
     * @param $submission Submission
     * @return DOMElement
     */
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

    /**
     * Create and return the conference paper node 'conference_paper'.
     * @param $doc DOMDocument
     * @param $submission Submission
     * @return DOMElement
     */
    public function createConferencePaperNode($doc, $submission)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $request = Application::get()->getRequest();

        $publication = $submission->getCurrentPublication();

        $locale = $publication->getData('locale');

        // Issue shoulld be set by now
        $issue = $deployment->getIssue();

        $conferencePaperNode = $doc->createElementNS($deployment->getNamespace(), 'conference_paper');
        $conferencePaperNode->setAttribute('publication_type', 'full_text');
        $conferencePaperNode->setAttribute('metadata_distribution_opts', 'any');

        // title
        $titlesNode = $doc->createElementNS($deployment->getNamespace(), 'titles');
        $titlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($publication->getData('title', $locale), ENT_COMPAT, 'UTF-8')));
        if ($subtitle = $publication->getData('subtitle', $locale)) {
            $titlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'subtitle', htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8')));
        }
        $conferencePaperNode->appendChild($titlesNode);

        //contributors
        $authors = $publication->getData('authors');

        if(!empty($author)) {
            $contributorsNode = $doc->createElementNS($deployment->getNamespace(), 'contributors');
            $isFirst = true;

            foreach ($authors as $author) { /** @var $author Author */
                $personNameNode = $doc->createElementNS($deployment->getNamespace(), 'person_name');
                $personNameNode->setAttribute('contributor_role', 'author');

                if ($isFirst) {
                    $personNameNode->setAttribute('sequence', 'first');
                } else {
                    $personNameNode->setAttribute('sequence', 'additional');
                }

                $familyNames = $author->getFamilyName(null);
                $givenNames = $author->getGivenName(null);

                // Check if both givenName and familyName is set for the submission language.
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

        // publication date
        if ($datePublished = $publication->getData('datePublished')) {
            $conferencePaperNode->appendChild($this->createPublicationDateNode($doc, $datePublished));
        }

        // DOI data
        $doiDataNode = $this->createDOIDataNode($doc, $publication->getStoredPubId('doi'), $request->url($context->getPath(), 'article', 'view', $submission->getBestId(), null, null, true));
        // append galleys files and collection nodes to the DOI data node
        $galleys = $publication->getData('galleys');
        // All full-texts, PDF full-texts and remote galleys for text-mining and as-crawled URL
        $submissionGalleys = $pdfGalleys = $remoteGalleys = array();
        // preferred PDF full-text for the as-crawled URL
        $pdfGalleyInArticleLocale = null;
        // get immediatelly also supplementary files for component list
        $componentGalleys = array();
        $genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */

        foreach ($galleys as $galley) {
            // filter supp files with DOI
            if (!$galley->getRemoteURL()) {
                $galleyFile = $galley->getFile();
                if ($galleyFile) {
                    $genre = $genreDao->getById($galleyFile->getGenreId());
                    if ($genre->getSupplementary()) {
                        if ($galley->getStoredPubid('doi')) {
                            // construct the array key with galley best ID and locale needed for the component node
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

        // as-crawled URLs
        $asCrawledGalleys = array();
        if ($pdfGalleyInArticleLocale) {
            $asCrawledGalleys = array($pdfGalleyInArticleLocale);
        } elseif (!empty($pdfGalleys)) {
            $asCrawledGalleys = array($pdfGalleys[0]);
        } else {
            $asCrawledGalleys = $submissionGalleys;
        }

        // as-crawled URL - collection nodes
        $this->appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $asCrawledGalleys);

        // text-mining - collection nodes
        $submissionGalleys = array_merge($submissionGalleys, $remoteGalleys);
        $this->appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $submissionGalleys);
        $conferencePaperNode->appendChild($doiDataNode);

        return $conferencePaperNode;

    }

    /**
     * Append the collection node 'collection property="crawler-based"' to the doi data node.
     * @param $doc DOMDocument
     * @param $doiDataNode DOMElement
     * @param $submission Submission
     * @param $galleys array of galleys
     */
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
            // iParadigms crawler based collection element
            $crawlerBasedCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
            $crawlerBasedCollectionNode->setAttribute('property', 'crawler-based');
            $iParadigmsItemNode = $doc->createElementNS($deployment->getNamespace(), 'item');
            $iParadigmsItemNode->setAttribute('crawler', 'iParadigms');
            $iParadigmsItemNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $resourceURL));
            $crawlerBasedCollectionNode->appendChild($iParadigmsItemNode);
            $doiDataNode->appendChild($crawlerBasedCollectionNode);
        }
    }

    /**
     * Append the collection node 'collection property="text-mining"' to the doi data node.
     * @param $doc DOMDocument
     * @param $doiDataNode DOMElement
     * @param $submission Submission
     * @param $galleys array of galleys
     */
    public function appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $galleys)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $request = Application::get()->getRequest();

        $textMiningCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
        $textMiningCollectionNode->setAttribute('property', 'text-mining');
        foreach ($galleys as $galley) {
            $resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $galley->getBestGalleyId()), null, null, true);
            // text-mining collection item
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
