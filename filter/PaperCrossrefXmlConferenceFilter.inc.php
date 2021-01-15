<?php

/**
 * @file plugins/importexport/crossref/filter/PaperCrossrefXmlConferenceFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PaperCrossrefXmlConferenceFilter
 * @ingroup plugins_importexport_crossref
 *
 * @brief Class that converts an Paper to a Crossref XML document.
 */

import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');

class PaperCrossrefXmlConferenceFilter extends IssueCrossrefXmlConferenceFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Crossref XML article export');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter';
	}


	//
	// Submission conversion functions
	//
	/**
	 * @copydoc IssueCrossrefXmlConferenceFilter::createConferenceNode()
	 */
	function createConferenceNode($doc, $pubObject) {
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
	function createProceedingsSeriesMetadataNode($doc, $submission) {
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
			if ($issue) $cache->add($issue, null);
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
	function createConferencePaperNode($doc, $submission) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::get()->getRequest();
		
		$publication = $submission->getCurrentPublication();
		$locale = $publication->getData('locale');
		
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); 
		$submissionTest = $submissionDao->getByPubId('doi','7',$context->getId());
		print_r($submissionTest);

		// Issue shoulld be set by now
		$issue = $deployment->getIssue();

		$conferencePaperNode = $doc->createElement('conference_paper');
		$conferencePaperNode->setAttribute('publication_type', 'full_text');
		//$conferencePaperNode->setAttribute('metadata_distribution_opts', 'any');

		//contributors
		$contributorsNode = $doc->createElement('contributors');
		$authors = $publication->getData('authors');
		$isFirst = true;
		foreach ($authors as $author) { /** @var $author Author */
			$personNameNode = $doc->createElement('person_name');
			$personNameNode->setAttribute('contributor_role', 'author');

			if ($isFirst) {
				$personNameNode->setAttribute('sequence', 'first');
			} else {
				$personNameNode->setAttribute('sequence', 'additional');
			}

			$familyNames = $author->getFamilyName(null);
			$givenNames = $author->getGivenName(null);

			$contributorsNode->appendChild($personNameNode);

			if (isset($familyNames[$locale]) && isset($givenNames[$locale])) {
				$personNameNode->setAttribute('language', PKPLocale::getIso1FromLocale($locale));
				$personNameNode->appendChild($node = $doc->createElement('given_name', htmlspecialchars(ucfirst($givenNames[$locale]), ENT_COMPAT, 'UTF-8')));
				$personNameNode->appendChild($node = $doc->createElement('surname', htmlspecialchars(ucfirst($familyNames[$locale]), ENT_COMPAT, 'UTF-8')));
			} else {
				$personNameNode->appendChild($node = $doc->createElement('surname', htmlspecialchars(ucfirst($author->getFullName(false)), ENT_COMPAT, 'UTF-8')));
			}

			$contributorsNode->appendChild($personNameNode);
			$isFirst = false;
		} 

		$conferencePaperNode->appendChild($contributorsNode);

		// title
		$titlesNode = $doc->createElement('titles');
		$titlesNode->appendChild($node = $doc->createElement('title', htmlspecialchars($publication->getData('title', $locale), ENT_COMPAT, 'UTF-8')));
		if ($subtitle = $publication->getData('subtitle', $locale)) $titlesNode->appendChild($node = $doc->createElement('subtitle', htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8')));
		$conferencePaperNode->appendChild($titlesNode);
		

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
		// text-mining - collection nodes
		$submissionGalleys = array_merge($submissionGalleys, $remoteGalleys);
		$this->appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $submissionGalleys);
		$conferencePaperNode->appendChild($doiDataNode);

		return $conferencePaperNode;
	
	}

	/**
	 * Append the collection node 'collection property="text-mining"' to the doi data node.
	 * @param $doc DOMDocument
	 * @param $doiDataNode DOMElement
	 * @param $submission Submission
	 * @param $galleys array of galleys
	 */
	function appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $galleys) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::get()->getRequest();

		// start of the text-mining collection element
		$textMiningCollectionNode = $doc->createElement('collection');
		$textMiningCollectionNode->setAttribute('property', 'text-mining');
		foreach ($galleys as $galley) {
			$resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $galley->getBestGalleyId()), null, null, true);
			// text-mining collection item
			$textMiningItemNode = $doc->createElement('item');
			$resourceNode = $doc->createElement('resource', $resourceURL);
			if (!$galley->getRemoteURL()) $resourceNode->setAttribute('mime_type', $galley->getFileType());
			$textMiningItemNode->appendChild($resourceNode);
			$textMiningCollectionNode->appendChild($textMiningItemNode);
		}
		$doiDataNode->appendChild($textMiningCollectionNode);
	}
}


