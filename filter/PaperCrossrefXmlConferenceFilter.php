<?php

/**
 * @class PaperCrossrefXmlConferenceFilter
 * @ingroup plugins_importexport_crossrefConference
 *
 * @brief Class that converts an Paper to a Crossref Conference XML document.
 */

namespace APP\plugins\generic\crossrefConference\filter;

use APP\core\Application;
use APP\submission\Submission;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;

class PaperCrossrefXmlConferenceFilter extends ProceedingsCrossrefXmlConferenceFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Crossref XML paper export');
        parent::__construct($filterGroup);
    }

    public function createConferenceNode($doc, $pubObject)
    {
        $conferenceNode = parent::createConferenceNode($doc, $pubObject);
        assert($pubObject instanceof Submission);
        $conferenceNode->appendChild($this->createConferencePaperNode($doc, $pubObject));
        return $conferenceNode;
    }

    public function createProceedingsSeriesMetadataNode($doc, $submission)
    {
        assert($submission instanceof Submission);
        $issue = $this->getIssueOfSubmission($submission);
        $proceedingsSeriesMetadataNode = parent::createProceedingsSeriesMetadataNode($doc, $issue);
        return $proceedingsSeriesMetadataNode;
    }

    public function createConferencePaperNode($doc, $submission)
    {
        $deployment = $this->getDeployment();

        $publication = $submission->getCurrentPublication();
        $locale = $publication->getData('locale');

        $conferencePaperNode = $doc->createElementNS($deployment->getNamespace(), 'conference_paper');
        $conferencePaperNode->setAttribute('publication_type', 'full_text');
        $conferencePaperNode->setAttribute('metadata_distribution_opts', 'any');

        if (count($publication->getData('authors') ?? []) > 0) {
            $this->addContributors($doc, $conferencePaperNode, $publication);
        }

        $this->addTitles($doc, $conferencePaperNode, $publication);

        if ($datePublished = $publication->getData('datePublished')) {
            $conferencePaperNode->appendChild($this->createPublicationDateNode($doc, $datePublished));
        }

        $doiDataNode = $this->createDOIDataNode($doc, $publication->getStoredPubId('doi'), $this->getArticlePageUrl('view', [$submission->getBestId()]));

        $galleys = $publication->getData('galleys') ?? [];

        $submissionGalleys = $pdfGalleys = $remoteGalleys = [];

        $pdfGalleyInArticleLocale = null;

        $componentGalleys = [];
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */

        foreach ($galleys as $galley) {
            if (!$galley->getData('urlRemote')) {
                $galleyFile = $galley->getFile();
                if ($galleyFile) {
                    $genre = $genreDao->getById($galleyFile->getGenreId());
                    if ($genre->getSupplementary()) {
                        if ($galley->getStoredPubId('doi')) {
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

        $asCrawledGalleys = [];
        if ($pdfGalleyInArticleLocale) {
            $asCrawledGalleys = [$pdfGalleyInArticleLocale];
        } elseif (!empty($pdfGalleys)) {
            $asCrawledGalleys = [$pdfGalleys[0]];
        } else {
            $asCrawledGalleys = $submissionGalleys;
        }

        $this->appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $asCrawledGalleys);

        $submissionGalleys = array_merge($submissionGalleys, $remoteGalleys);
        $this->appendTextMiningCollectionNodes($doc, $doiDataNode, $submission, $submissionGalleys);
        $conferencePaperNode->appendChild($doiDataNode);

        return $conferencePaperNode;
    }

    public function addTitles($doc, $conferencePaperNode, $publication)
    {
        $deployment = $this->getDeployment();
        $locale = $publication->getData('locale');

        $conferencePaperNode->appendChild($titlesNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'titles'
        ));
        $titlesNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'title',
            htmlspecialchars((string) $publication->getData('title', $locale), ENT_COMPAT, 'UTF-8')
        ));
        if ($subtitle = $publication->getData('subtitle', $locale)) {
            $titlesNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'subtitle',
                htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8')
            ));
        }
    }

    public function addContributors($doc, $conferencePaperNode, $publication)
    {
        $deployment = $this->getDeployment();
        $locale = $publication->getData('locale');
        $isFirst = true;

        $conferencePaperNode->appendChild($contributorsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'contributors'
        ));

        $authors = $publication->getData('authors');
        foreach ($authors as $author) {
            $contributorsNode->appendChild($personNameNode = $doc->createElementNS(
                $deployment->getNamespace(),
                'person_name'
            ));
            $personNameNode->setAttribute('contributor_role', 'author');

            $sequence = 'additional';
            if ($isFirst) {
                $sequence = 'first';
            }
            $personNameNode->setAttribute('sequence', $sequence);

            $familyNames = $author->getFamilyName(null) ?? [];
            $givenNames = $author->getGivenName(null) ?? [];
            if (!empty($familyNames[$locale]) && !empty($givenNames[$locale])) {
                $personNameNode->setAttribute('language', \Locale::getPrimaryLanguage($locale));
                $personNameNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'given_name',
                    htmlspecialchars(ucfirst($givenNames[$locale]), ENT_COMPAT, 'UTF-8')
                ));
                $personNameNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'surname',
                    htmlspecialchars(ucfirst($familyNames[$locale]), ENT_COMPAT, 'UTF-8')
                ));
                $hasAltName = false;

                if ($author->getData('orcid')) {
                    $personNameNode->appendChild($node = $doc->createElementNS(
                        $deployment->getNamespace(),
                        'ORCID',
                        $author->getData('orcid')
                    ));
                }

                foreach ($familyNames as $otherLocal => $familyName) {
                    if ($otherLocal != $locale && isset($familyName) && !empty($familyName)) {
                        if (!$hasAltName) {
                            $altNameNode = $doc->createElementNS($deployment->getNamespace(), 'alt-name');
                            $personNameNode->appendChild($altNameNode);

                            $hasAltName = true;
                        }

                        $nameNode = $doc->createElementNS($deployment->getNamespace(), 'name');
                        $nameNode->setAttribute('language', \Locale::getPrimaryLanguage($otherLocal));

                        $nameNode->appendChild($node = $doc->createElementNS(
                            $deployment->getNamespace(),
                            'surname',
                            htmlspecialchars(ucfirst($familyName), ENT_COMPAT, 'UTF-8')
                        ));
                        if (isset($givenNames[$otherLocal]) && !empty($givenNames[$otherLocal])) {
                            $nameNode->appendChild($node = $doc->createElementNS(
                                $deployment->getNamespace(),
                                'given_name',
                                htmlspecialchars(ucfirst($givenNames[$otherLocal]), ENT_COMPAT, 'UTF-8')
                            ));
                        }

                        $altNameNode->appendChild($nameNode);
                    }
                }
            } else {
                $personNameNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'surname',
                    htmlspecialchars(ucfirst((string) ($givenNames[$locale] ?? '')), ENT_COMPAT, 'UTF-8')
                ));
            }

            $isFirst = false;
        }
    }

    public function appendAsCrawledCollectionNodes($doc, $doiDataNode, $submission, $galleys)
    {
        $deployment = $this->getDeployment();

        if (empty($galleys)) {
            $crawlerBasedCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
            $crawlerBasedCollectionNode->setAttribute('property', 'crawler-based');
            $doiDataNode->appendChild($crawlerBasedCollectionNode);
        }
        foreach ($galleys as $galley) {
            $resourceURL = $this->getArticlePageUrl('download', [$submission->getBestId(), $galley->getBestGalleyId()]);
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

        $textMiningCollectionNode = $doc->createElementNS($deployment->getNamespace(), 'collection');
        $textMiningCollectionNode->setAttribute('property', 'text-mining');
        foreach ($galleys as $galley) {
            $resourceURL = $this->getArticlePageUrl('download', [$submission->getBestId(), $galley->getBestGalleyId()]);
            $textMiningItemNode = $doc->createElementNS($deployment->getNamespace(), 'item');
            $resourceNode = $doc->createElementNS($deployment->getNamespace(), 'resource', $resourceURL);
            if (!$galley->getData('urlRemote')) {
                $resourceNode->setAttribute('mime_type', $galley->getFileType());
            }
            $textMiningItemNode->appendChild($resourceNode);
            $textMiningCollectionNode->appendChild($textMiningItemNode);
        }
        $doiDataNode->appendChild($textMiningCollectionNode);
    }

    protected function getArticlePageUrl(string $op, array $path): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $this->getDeployment()->getContext()->getPath(),
            'article',
            $op,
            array_map('strval', $path),
            null,
            null,
            true,
            ''
        );
    }
}
