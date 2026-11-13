<?php

require_once __DIR__ . '/CrossrefConferenceTestCase.php';

use APP\plugins\generic\crossrefConference\filter\ProceedingsCrossrefXmlConferenceFilter;

class ProceedingsCrossrefXmlConferenceFilterTest extends CrossrefConferenceTestCase
{
    private DOMDocument $doc;
    private ProceedingsCrossrefXmlConferenceFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doc = new DOMDocument('1.0', 'utf-8');
        $this->filter = $this->createFilter(ProceedingsCrossrefXmlConferenceFilter::class);
    }

    public function testRootNodeDeclaresCrossrefSchema436(): void
    {
        $rootNode = $this->filter->createRootNode($this->doc);

        $this->assertSame('doi_batch', $rootNode->localName);
        $this->assertSame(self::NAMESPACE_URI, $rootNode->namespaceURI);
        $this->assertSame('4.3.6', $rootNode->getAttribute('version'));
        $this->assertSame(
            self::NAMESPACE_URI . ' http://www.crossref.org/schema/deposit/crossref4.3.6.xsd',
            $rootNode->getAttribute('xsi:schemaLocation')
        );
    }

    public function testHeadNodeIdentifiesDepositorAndRegistrant(): void
    {
        $headNode = $this->filter->createHeadNode($this->doc);

        $this->assertStringStartsWith('proceeding_', $this->childText($headNode, 'doi_batch_id'));
        $this->assertMatchesRegularExpression('/^\d{17}$/', $this->childText($headNode, 'timestamp'));
        $this->assertSame('Lepidus Tecnologia', $this->childText($headNode, 'depositor_name'));
        $this->assertSame('doi@lepidus.com.br', $this->childText($headNode, 'email_address'));
        $this->assertSame('SBMAC', $this->childText($headNode, 'registrant'));
    }

    public function testHeadNodeFallsBackToJournalSupportContactWithoutDepositorSettings(): void
    {
        unset($this->pluginSettings['depositorName'], $this->pluginSettings['depositorEmail']);

        $headNode = $this->filter->createHeadNode($this->doc);

        $this->assertSame('Suporte SBMAC', $this->childText($headNode, 'depositor_name'));
        $this->assertSame('suporte@sbmac.org.br', $this->childText($headNode, 'email_address'));
    }

    public function testConferenceNameIsTheCustomNameWhenConfigured(): void
    {
        $eventMetadataNode = $this->filter->createEventMetadataNode($this->doc, $this->createIssue());

        $this->assertSame(
            'CNMAC 2019 - XXXIX Congresso Nacional de Matemática Aplicada e Computacional',
            $this->childText($eventMetadataNode, 'conference_name')
        );
    }

    public function testConferenceNameIsTheIssueTitleWhenConfigured(): void
    {
        $this->pluginSettings['conferenceNameOption'] = 'issueTitle';

        $eventMetadataNode = $this->filter->createEventMetadataNode($this->doc, $this->createIssue());

        $this->assertSame('CNMAC 2019', $this->childText($eventMetadataNode, 'conference_name'));
    }

    public function testEventMetadataHasLocationAndDatesOfTheIssue(): void
    {
        $issue = $this->createIssue([
            'conferencePlaceCity' => 'Uberlândia',
            'conferencePlaceCountry' => 'Brasil',
            'conferenceDateBegin' => '2019-09-16',
            'conferenceDateEnd' => '2019-09-20',
        ]);

        $eventMetadataNode = $this->filter->createEventMetadataNode($this->doc, $issue);
        $conferenceDateNode = $this->child($eventMetadataNode, 'conference_date');

        $this->assertSame('Uberlândia, Brasil', $this->childText($eventMetadataNode, 'conference_location'));
        $this->assertSame(
            ['16', '09', '2019', '20', '09', '2019'],
            array_map(fn ($attribute) => $conferenceDateNode->getAttribute($attribute), ['start_day', 'start_month', 'start_year', 'end_day', 'end_month', 'end_year'])
        );
    }

    public function testEventMetadataHasOnlyTheNameWhenIssueHasNoLocationOrDates(): void
    {
        $eventMetadataNode = $this->filter->createEventMetadataNode($this->doc, $this->createIssue());

        $this->assertNull($this->child($eventMetadataNode, 'conference_location'));
        $this->assertNull($this->child($eventMetadataNode, 'conference_date'));
    }

    public function testProceedingsSeriesMetadataDescribesTheJournal(): void
    {
        $seriesNode = $this->filter->createProceedingsSeriesMetadataNode($this->doc, $this->createIssue());
        $issnNode = $this->child($seriesNode, 'issn');
        $publicationDateNode = $this->child($seriesNode, 'publication_date');

        $this->assertSame('Proceeding Series of the Brazilian Society of Computational and Applied Mathematics', $this->childText($seriesNode, 'title'));
        $this->assertSame('2359-0793', $issnNode->textContent);
        $this->assertSame('electronic', $issnNode->getAttribute('media_type'));
        $this->assertSame('SBMAC', $this->childText($seriesNode, 'publisher_name'));
        $this->assertSame(['02', '20', '2020'], [
            $this->childText($publicationDateNode, 'month'),
            $this->childText($publicationDateNode, 'day'),
            $this->childText($publicationDateNode, 'year'),
        ]);
    }
}
