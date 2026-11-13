<?php

require_once __DIR__ . '/CrossrefConferenceTestCase.php';

use APP\plugins\generic\crossrefConference\filter\PaperCrossrefXmlConferenceFilter;
use PKP\galley\Galley;

class PaperCrossrefXmlConferenceFilterTest extends CrossrefConferenceTestCase
{
    private DOMDocument $doc;
    private PaperCrossrefXmlConferenceFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doc = new DOMDocument('1.0', 'utf-8');
        $this->filter = $this->createFilter(PaperCrossrefXmlConferenceFilter::class);
    }

    public function testContributorsAreListedInAuthorOrder(): void
    {
        $submission = $this->createSubmission([
            $this->createAuthor(['pt_BR' => 'amanda', 'en' => 'Amanda'], ['pt_BR' => 'aléssio', 'en' => 'Alessio'], 'https://orcid.org/0000-0001-2345-6789'),
            $this->createAuthor(['pt_BR' => 'Cristiane'], ['pt_BR' => 'Nespoli']),
        ]);

        $paperNode = $this->filter->createConferencePaperNode($this->doc, $submission);
        $firstAuthorNode = $this->child($paperNode, 'person_name', 0);
        $secondAuthorNode = $this->child($paperNode, 'person_name', 1);
        $alternativeNameNode = $this->child($firstAuthorNode, 'name');

        $this->assertSame(['author', 'first', 'pt'], [
            $firstAuthorNode->getAttribute('contributor_role'),
            $firstAuthorNode->getAttribute('sequence'),
            $firstAuthorNode->getAttribute('language'),
        ]);
        $this->assertSame('Amanda', $this->childText($firstAuthorNode, 'given_name'));
        $this->assertSame('Aléssio', $this->childText($firstAuthorNode, 'surname'));
        $this->assertSame('https://orcid.org/0000-0001-2345-6789', $this->childText($firstAuthorNode, 'ORCID'));
        $this->assertSame('en', $alternativeNameNode->getAttribute('language'));
        $this->assertSame('Alessio', $this->childText($alternativeNameNode, 'surname'));
        $this->assertSame('additional', $secondAuthorNode->getAttribute('sequence'));
        $this->assertSame('Cristiane', $this->childText($secondAuthorNode, 'given_name'));
        $this->assertSame('Nespoli', $this->childText($secondAuthorNode, 'surname'));
    }

    public function testPaperWithoutAuthorsHasNoContributors(): void
    {
        $paperNode = $this->filter->createConferencePaperNode($this->doc, $this->createSubmission());

        $this->assertNull($this->child($paperNode, 'contributors'));
    }

    public function testPaperHasTitleSubtitleAndPublicationDate(): void
    {
        $paperNode = $this->filter->createConferencePaperNode($this->doc, $this->createSubmission());
        $publicationDateNode = $this->child($paperNode, 'publication_date');

        $this->assertSame(['full_text', 'any'], [$paperNode->getAttribute('publication_type'), $paperNode->getAttribute('metadata_distribution_opts')]);
        $this->assertSame('A importância do Cálculo Diferencial e Integral', $this->childText($paperNode, 'title'));
        $this->assertSame('para a formação do professor de Matemática', $this->childText($paperNode, 'subtitle'));
        $this->assertSame(['02', '20', '2020'], [
            $this->childText($publicationDateNode, 'month'),
            $this->childText($publicationDateNode, 'day'),
            $this->childText($publicationDateNode, 'year'),
        ]);
    }

    public function testDoiDataPointsToTheArticlePage(): void
    {
        $doiDataNode = $this->child($this->filter->createConferencePaperNode($this->doc, $this->createSubmission()), 'doi_data');

        $this->assertSame('10.5540/03.2020.007.01.0339', $this->childText($doiDataNode, 'doi'));
        $this->assertStringEndsWith('/sbmac/article/view/2680', $this->childText($doiDataNode, 'resource'));
    }

    public function testPaperWithoutGalleysHasEmptyCollections(): void
    {
        $doiDataNode = $this->child($this->filter->createConferencePaperNode($this->doc, $this->createSubmission()), 'doi_data');

        $this->assertSame('crawler-based', $this->child($doiDataNode, 'collection', 0)->getAttribute('property'));
        $this->assertSame('text-mining', $this->child($doiDataNode, 'collection', 1)->getAttribute('property'));
        $this->assertNull($this->child($doiDataNode, 'item'));
    }

    public function testRemoteGalleyIsOfferedForTextMining(): void
    {
        $galley = new Galley();
        $galley->setData('id', 2700);
        $galley->setData('urlRemote', 'https://example.org/paper.pdf');

        $doiDataNode = $this->child($this->filter->createConferencePaperNode($this->doc, $this->createSubmission([], [$galley])), 'doi_data');
        $textMiningNode = $this->child($doiDataNode, 'collection', 1);
        $resourceNode = $this->child($textMiningNode, 'resource');

        $this->assertSame('text-mining', $textMiningNode->getAttribute('property'));
        $this->assertStringEndsWith('/sbmac/article/download/2680/2700', $resourceNode->textContent);
        $this->assertFalse($resourceNode->hasAttribute('mime_type'));
    }

    public function testProcessWrapsEachPaperInItsConference(): void
    {
        $this->exportPlugin->getCache()->add($this->createIssue(['conferencePlaceCity' => 'Uberlândia']), null);
        $submissions = [$this->createSubmission()];

        $doc = $this->filter->process($submissions);
        $conferenceNode = $this->child($doc->documentElement, 'conference');

        $this->assertSame('doi_batch', $doc->documentElement->localName);
        $this->assertNotNull($this->child($doc->documentElement, 'head'));
        $this->assertSame(
            ['event_metadata', 'proceedings_series_metadata', 'conference_paper'],
            array_values(array_map(fn ($node) => $node->localName, array_filter(iterator_to_array($conferenceNode->childNodes), fn ($node) => $node instanceof DOMElement)))
        );
        $this->assertSame('Uberlândia', $this->childText($conferenceNode, 'conference_location'));
    }
}
