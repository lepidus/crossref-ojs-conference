<?php


import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.ProceedingsCrossrefXmlConferenceFilter');
import('plugins.importexport.crossrefConference.filter.PaperCrossrefXmlConferenceFilter');
import('lib.pkp.classes.user.User');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.crossrefConference.tests.ContextMock');
import('plugins.importexport.crossrefConference.tests.PluginMock');
import('plugins.importexport.crossrefConference.CrossrefConferenceExportDeployment');
import('classes.issue.Issue');
import("classes.submission.Submission");

class ProceedingsCrossrefXmlConferenceFilterTest extends PKPTestCase
{
    private $expectedFile;
    private $doc;
    private $filterGroup;
    private $context;
    private $plugin;
    private $deployment;


    protected function setUp(): void
    {

        $this->expectedFile = new DOMDocument('1.0', 'utf-8');
        $this->expectedFile->loadXML($this->getTestData());
        $this->expectedFile->preserveWhiteSpace = true;
        $this->doc = new DOMDocument('1.0', 'utf-8');
        $this->doc->formatOutput = true;


        $this->filterGroup = new FilterGroup();

        $this->context = new ContextMock();
        $this->plugin = new PluginMock();
        $this->deployment = new CrossrefConferenceExportDeployment($this->context, $this->plugin);

        parent::setUp();
    }

    public function testCreateRootNode()
    {

        $this->expectedFile->loadXML(file_get_contents('./plugins/importexport/crossrefConference/tests/rootConference-test.xml'));

        $crossRef = new ProceedingsCrossrefXmlConferenceFilter($this->filterGroup);
        $crossRef->setDeployment($this->deployment);
        $doiBatch = $crossRef->createRootNode($this->doc);
        $this->doc->appendChild($doiBatch);

        $expected = $this->expectedFile->getElementsByTagName("doi_batch")->item(0);

        $actual = $this->doc->getElementsByTagName("doi_batch")->item(0);

        self::assertXmlStringEqualsXmlString(
            $this->expectedFile->saveXML($expected),
            $this->doc->saveXML($actual),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateHeadNode()
    {

        $crossRef = new ProceedingsCrossrefXmlConferenceFilter($this->filterGroup);
        $crossRef->setDeployment($this->deployment);

        $head = $crossRef->createHeadNode($this->doc);
        $head = $this->doc->appendChild($head);

        $expected = $this->expectedFile->getElementsByTagName('head')->item(0);

        $actual = $this->doc->getElementsByTagName("head")->item(0);

        $actualDoiBatch = $actual->getElementsByTagName('doi_batch_id')->item(0);
        $actualDoiBatch->textContent = 'proceeding_1606846180';

        $actualTimeStamp =  $actual->getElementsByTagName('timestamp')->item(0);
        $actualTimeStamp->textContent = '1606846180';

        $actualDepositor =  $actual->getElementsByTagName('depositor')->item(0);
        $actualDepositorName = $actualDepositor->getElementsByTagName('name')->item(0);
        $actualDepositorName->textContent = 'Lepidus Tecnologia';
        $actualDepositorEmail = $actualDepositor->getElementsByTagName('email_address')->item(0);
        $actualDepositorEmail->textContent = 'doi@lepidus.com.br';

        $actualRegistrant =  $actual->getElementsByTagName('registrant')->item(0);
        $actualRegistrant->textContent = 'SBMAC';

        self::assertXmlStringEqualsXmlString(
            $this->expectedFile->saveXML($expected),
            $this->doc->saveXML($actual)
        );

    }

    public function testCreateConferenceNode()
    {

        $JournalDAO =& DAORegistry::getDAO('JournalDAO');
        $contexts = $JournalDAO->getAll();
        $context = ($contexts->toArray())[0];

        $deployment = new CrossrefConferenceExportDeployment($context, $this->plugin);

        $crossRef = new ProceedingsCrossrefXmlConferenceFilter($this->filterGroup);
        $crossRef->setDeployment($deployment);

        $issue = new Issue();
        $issue->setDatePublished(date("Y/m/d"));

        $bodyNode = $this->doc->createElement('body');
        $conference = $crossRef->createConferenceNode($this->doc, $issue);
        $bodyNode->appendChild($conference);
        $this->doc->appendChild($bodyNode);

        $expected = $this->expectedFile->getElementsByTagName('body')->item(0);
        $conference = $expected->getElementsByTagName('conference')->item(0);
        $paper = $conference->getElementsByTagName('conference_paper')->item(0);
        $removePaper = $conference->removeChild($paper);


        $actual = $this->doc->getElementsByTagName("body")->item(0);
        $actualConference = $actual->getElementsByTagName('conference')->item(0);

        $actualEventMetada = $actualConference->getElementsByTagName('event_metadata')->item(0);
        $actualConferenceName = $actualEventMetada->getElementsByTagName('conference_name')->item(0);
        $actualConferenceName->textContent = 'CNMAC 2019 - XXXIX Congresso Nacional de Matemática Aplicada e Computacional';

        $actualProceedingsSeriesMetada = $actualConference->getElementsByTagName('proceedings_series_metadata')->item(0);

        $actualSeriesMetadata = $actualProceedingsSeriesMetada->getElementsByTagName('series_metadata')->item(0);

        $actualTitles = $actualSeriesMetadata->getElementsByTagName('titles')->item(0);
        $actualTitle = $actualTitles->getElementsByTagName('title')->item(0);
        $actualTitle->textContent = 'Proceeding Series of the Brazilian Society of Computational and Applied Mathematics';

        $actualISSN = $actualSeriesMetadata->getElementsByTagName('issn')->item(0);
        $actualISSN->textContent = '2359-0793';

        $actualPublisher = $actualProceedingsSeriesMetada->getElementsByTagName('publisher')->item(0);
        $actualPublisherName = $actualPublisher->getElementsByTagName('publisher_name')->item(0);
        $actualPublisherName->textContent = 'SBMAC';

        $actualPublicationDate = $actualProceedingsSeriesMetada->getElementsByTagName('publication_date')->item(0);
        $actualMonth = $actualPublicationDate->getElementsByTagName('month')->item(0);
        $actualMonth->textContent = '02';
        $actualDay = $actualPublicationDate->getElementsByTagName('day')->item(0);
        $actualDay->textContent = '20';
        $actualYear = $actualPublicationDate->getElementsByTagName('year')->item(0);
        $actualYear->textContent = '2020';

        self::assertXmlStringEqualsXmlString(
            $this->expectedFile->saveXML($expected),
            $this->doc->saveXML($actual),
            "actual xml is equal to expected xml"
        );

    }

    private function getTestData()
    {
        $sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
        return file_get_contents($sampleFile);
    }

}
