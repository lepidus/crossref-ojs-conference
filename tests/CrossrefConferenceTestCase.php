<?php

use APP\author\Author;
use APP\issue\Issue;
use APP\journal\Journal;
use APP\plugins\generic\crossrefConference\CrossrefConferenceExportDeployment;
use APP\plugins\generic\crossrefConference\CrossrefConferenceExportPlugin;
use APP\plugins\generic\crossrefConference\CrossrefConferencePlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\doi\Doi;
use PKP\filter\FilterGroup;
use PKP\tests\PKPTestCase;

abstract class CrossrefConferenceTestCase extends PKPTestCase
{
    protected const NAMESPACE_URI = 'http://www.crossref.org/schema/4.3.6';

    protected array $pluginSettings = [];
    protected Journal $journal;
    protected CrossrefConferenceExportPlugin $exportPlugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequest();

        $this->pluginSettings = [
            'depositorName' => 'Lepidus Tecnologia',
            'depositorEmail' => 'doi@lepidus.com.br',
            'conferenceNameOption' => 'custom',
            'conferenceName' => 'CNMAC 2019 - XXXIX Congresso Nacional de Matemática Aplicada e Computacional',
        ];
        $this->journal = $this->createJournal();
        $this->exportPlugin = new CrossrefConferenceExportPlugin($this->createAgencyPlugin());
    }

    protected function createAgencyPlugin(): CrossrefConferencePlugin
    {
        $testCase = $this;
        return new class ($testCase) extends CrossrefConferencePlugin {
            public function __construct(private CrossrefConferenceTestCase $testCase)
            {
                parent::__construct();
            }

            public function getSetting($contextId, $name)
            {
                return $this->testCase->getPluginSetting($name);
            }
        };
    }

    public function getPluginSetting(string $name): mixed
    {
        return $this->pluginSettings[$name] ?? null;
    }

    protected function createJournal(): Journal
    {
        $journal = new Journal();
        $journal->setData('id', 1);
        $journal->setData('urlPath', 'sbmac');
        $journal->setData('primaryLocale', 'pt_BR');
        $journal->setData('name', 'Proceeding Series of the Brazilian Society of Computational and Applied Mathematics', 'pt_BR');
        $journal->setData('onlineIssn', '2359-0793');
        $journal->setData('publisherInstitution', 'SBMAC');
        $journal->setData('supportName', 'Suporte SBMAC');
        $journal->setData('supportEmail', 'suporte@sbmac.org.br');
        return $journal;
    }

    protected function createIssue(array $conferenceData = []): Issue
    {
        $issue = new Issue();
        $issue->setData('id', 7);
        $issue->setData('title', 'CNMAC 2019', 'pt_BR');
        $issue->setData('datePublished', '2020-02-20 00:00:00');
        foreach ($conferenceData as $name => $value) {
            $issue->setData($name, $value);
        }
        return $issue;
    }

    protected function createAuthor(array $givenNames, array $familyNames, ?string $orcid = null): Author
    {
        $author = new Author();
        foreach ($givenNames as $locale => $givenName) {
            $author->setGivenName($givenName, $locale);
        }
        foreach ($familyNames as $locale => $familyName) {
            $author->setFamilyName($familyName, $locale);
        }
        $author->setData('orcid', $orcid);
        return $author;
    }

    protected function createSubmission(array $authors = [], array $galleys = []): Submission
    {
        $doi = new Doi();
        $doi->setData('doi', '10.5540/03.2020.007.01.0339');

        $publication = new Publication();
        $publication->setData('id', 30);
        $publication->setData('locale', 'pt_BR');
        $publication->setData('issueId', 7);
        $publication->setData('title', 'A importância do Cálculo Diferencial e Integral', 'pt_BR');
        $publication->setData('subtitle', 'para a formação do professor de Matemática', 'pt_BR');
        $publication->setData('datePublished', '2020-02-20');
        $publication->setData('doiObject', $doi);
        $publication->setData('authors', collect($authors));
        $publication->setData('galleys', collect($galleys));

        $submission = new Submission();
        $submission->setData('id', 2680);
        $submission->setData('currentPublicationId', 30);
        $submission->setData('publications', collect([$publication]));
        return $submission;
    }

    protected function createDeployment(): CrossrefConferenceExportDeployment
    {
        return new CrossrefConferenceExportDeployment($this->journal, $this->exportPlugin);
    }

    protected function createFilter(string $filterClass)
    {
        $filterGroup = new FilterGroup();
        $filterGroup->setInputType('class::classes.submission.Submission[]');
        $filterGroup->setOutputType('xml::schema(https://www.crossref.org/schemas/crossref4.3.6.xsd)');

        $filter = new $filterClass($filterGroup);
        $filter->setDeployment($this->createDeployment());
        return $filter;
    }

    protected function childText(DOMElement $parent, string $tagName, int $index = 0): ?string
    {
        return $parent->getElementsByTagNameNS(self::NAMESPACE_URI, $tagName)->item($index)?->textContent;
    }

    protected function child(DOMElement $parent, string $tagName, int $index = 0): ?DOMElement
    {
        return $parent->getElementsByTagNameNS(self::NAMESPACE_URI, $tagName)->item($index);
    }
}
