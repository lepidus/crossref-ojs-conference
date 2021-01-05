<?php

/**
 * @file plugins/importexport/crossref/filter/ArticleCrossrefXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleCrossrefXmlFilter
 * @ingroup plugins_importexport_crossref
 *
 * @brief Class that converts an Article to a Crossref XML document.
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
		return 'plugins.importexport.crossref.filter.PaperCrossrefXmlConferenceFilter';
	}


	//
	// Submission conversion functions
	//
	/**
	 * @copydoc IssueCrossrefXmlFilter::createJournalNode()
	 */
	function createConferenceNode($doc, $pubObject) {
		$deployment = $this->getDeployment();
		$conferenceNode = parent::createConferenceNode($doc, $pubObject);
		assert(is_a($pubObject, 'Submission'));
		$conferenceNode->appendChild($this->createConferencePaperNode($doc, $pubObject));
		return $conferenceNode;
	}

	/**
	 * Create and return the journal issue node 'journal_issue'.
	 * @param $doc DOMDocument
	 * @param $submission Submission
	 * @return DOMElement
	 */
	function createJournalIssueNode($doc, $submission) {
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
		$journalIssueNode = parent::createJournalIssueNode($doc, $issue);
		return $journalIssueNode;
	}

	/**
	 * Create and return the journal article node 'journal_article'.
	 * @param $doc DOMDocument
	 * @param $submission Submission
	 * @return DOMElement
	 */
	function createConferencePaperNode($doc, $submission) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::get()->getRequest();

		$publication = $submission->getCurrentPublication();
		//$locale = $publication->getData('locale');

		// Issue shoulld be set by now
		$issue = $deployment->getIssue();

		$conferencePaperNode = $doc->createElement('conference_paper');
		$conferencePaperNode->setAttribute('publication_type', 'full_text');
		$conferencePaperNode->setAttribute('metadata_distribution_opts', 'any');

		$contributorsNode = $doc->createElement('contributors');
		/*
		$authors = $publication->getData('authors');
		$isFirst = true;
		foreach ($authors as $author) { /** @var $author Author 
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
		}
		*/
		$conferencePaperNode->appendChild($contributorsNode);



		return $conferencePaperNode;
	}

	/**
	 * Append the collection node 'collection property="crawler-based"' to the doi data node.
	 * @param $doc DOMDocument
	 * @param $doiDataNode DOMElement
	 * @param $submission Submission
	 * @param $galleys array of galleys
	 */
	function appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $galleys) {
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
	function appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $galleys) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::get()->getRequest();

		// start of the text-mining collection element
		$textMiningCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
		$textMiningCollectionNode->setAttribute('property', 'text-mining');
		foreach ($galleys as $galley) {
			$resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $galley->getBestGalleyId()), null, null, true);
			// text-mining collection item
			$textMiningItemNode = $doc->createElementNS($deployment->getNamespace(), 'item');
			$resourceNode = $doc->createElementNS($deployment->getNamespace(), 'resource', $resourceURL);
			if (!$galley->getRemoteURL()) $resourceNode->setAttribute('mime_type', $galley->getFileType());
			$textMiningItemNode->appendChild($resourceNode);
			$textMiningCollectionNode->appendChild($textMiningItemNode);
		}
		$doiDataNode->appendChild($textMiningCollectionNode);
	}

	/**
	 * Create and return component list node 'component_list'.
	 * @param $doc DOMDocument
	 * @param $submission Submission
	 * @param $componentGalleys array
	 * @return DOMElement
	 */
	function createComponentListNode($doc, $submission, $componentGalleys) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::get()->getRequest();

		// Create the base node
		$componentListNode =$doc->createElementNS($deployment->getNamespace(), 'component_list');
		// Run through supp files and add component nodes.
		foreach($componentGalleys as $componentGalley) {
			$componentFile = $componentGalley->getFile();
			$componentNode = $doc->createElementNS($deployment->getNamespace(), 'component');
			$componentNode->setAttribute('parent_relation', 'isPartOf');
			/* Titles */
			$componentFileTitle = $componentFile->getData('name', $componentGalley->getLocale());
			if (!empty($componentFileTitle)) {
				$titlesNode = $doc->createElementNS($deployment->getNamespace(), 'titles');
				$titlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($componentFileTitle, ENT_COMPAT, 'UTF-8')));
				$componentNode->appendChild($titlesNode);
			}
			// DOI data node
			$resourceURL = $request->url($context->getPath(), 'article', 'download', array($submission->getBestId(), $componentGalley->getBestGalleyId()), null, null, true);
			$componentNode->appendChild($this->createDOIDataNode($doc, $componentGalley->getStoredPubId('doi'), $resourceURL));
			$componentListNode->appendChild($componentNode);
		}
		return $componentListNode;
	}
}


