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
import("classes.publication.Publication");
import("classes.journal.JournalDAO");

class PaperCrossrefXmlConferenceFilterTest extends PKPTestCase {

	private $expectedFile;
	private $doc; 
	private $filterGroup;
	private $context;
	private $plugin;
	private $deployment;

	protected function setUp() : void {

		$this->expectedFile = new DOMDocument('1.0', 'utf-8');
		$this->expectedFile->loadXML($this->getTestData());
		$this->expectedFile->preserveWhiteSpace = false;

		$this->doc = new DOMDocument('1.0', 'utf-8');
		$this->doc->formatOutput = true;

		$this->filterGroup = new FilterGroup();

		$this->context = new ContextMock();
		$this->plugin = new PluginMock();
		$this->deployment = new CrossrefConferenceExportDeployment($this->context,$this->plugin);

		parent::setUp();
	}

	public function testCreateConferencePaperNode(){

		$crossRef = new PaperCrossrefXmlConferenceFilter($this->filterGroup);
		$crossRef->setDeployment($this->deployment);

		$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
		$submissions = $submissionDao->getByContextId(1);
		$submission = $submissions->toArray();

		$conferencePaper = $crossRef->createConferencePaperNode($this->doc, $submission[0]);
		$this->doc->appendChild($conferencePaper);

		$body = $this->expectedFile->getElementsByTagName('body')->item(0);
		$conference = $body->getElementsByTagName('conference')->item(0);
		$expected = $conference->getElementsByTagName('conference_paper')->item(0);

		$actual = $this->doc->getElementsByTagName("conference_paper")->item(0);

		$actualContributors = $actual->getElementsByTagName("contributors")->item(0);
		$actualPersonName = $actualContributors->getElementsByTagName("person_name")->item(0);
		$actualGivenName = $actualPersonName->getElementsByTagName("given_name")->item(0);
		$actualGivenName->textContent = 'Amanda';
		$actualSurname = $actualPersonName->getElementsByTagName("surname")->item(0);
		$actualSurname->textContent = 'Aléssio';

		$actualPersonNameAdditional = $actualContributors->getElementsByTagName("person_name")->item(1);
		$actualGivenName = $actualPersonNameAdditional->getElementsByTagName("given_name")->item(0);
		$actualGivenName->textContent = 'Cristiane';
		$actualSurname = $actualPersonNameAdditional->getElementsByTagName("surname")->item(0);
		$actualSurname->textContent = 'Nespoli';

		$actualTitles = $actual->getElementsByTagName('titles')->item(0);
		$actualTitle = $actualTitles->getElementsByTagName('title')->item(0);
		$actualTitle->textContent = 'A importância do Cálculo Diferencial e Integral para a formação do professor de Matemática da Educação Básica';

		$actualPublicationDate = $actual->getElementsByTagName('publication_date')->item(0);
		$actualMonth = $actualPublicationDate->getElementsByTagName('month')->item(0);
		$actualMonth->textContent = '02';
		$actualDay = $actualPublicationDate->getElementsByTagName('day')->item(0);
		$actualDay->textContent = '20';
		$actualYear = $actualPublicationDate->getElementsByTagName('year')->item(0);
		$actualYear->textContent = '2020';


		$actualDoiData = $actual->getElementsByTagName("doi_data")->item(0);

		$actualDoi = $actualDoiData->getElementsByTagName("doi")->item(0);
		$actualDoi->textContent = '10.5540/03.2020.007.01.0339';

		$actualResource = $actualDoiData->getElementsByTagName("resource")->item(0);
		$actualResource->textContent = 'https://proceedings.sbmac.org.br/sbmac/article/view/2680';

		$actualCollection = $actualDoiData->getElementsByTagName("collection")->item(0);
		$actualItem = $actualCollection->getElementsByTagName("item")->item(0);
		$actualResource = $actualItem->getElementsByTagName("resource")->item(0);
		$actualResource->textContent = 'https://proceedings.sbmac.org.br/sbmac/article/viewFile/2680/2700';

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expected),
			$this->doc->saveXML($actual),
			"actual xml is equal to expected xml"
		);
		
	}

	public function testGenerateXMLFile(){

		$xml_file_name = './plugins/importexport/crossrefConference/tests/conferenceGenerate-teste.xml';

		$JournalDAO =& DAORegistry::getDAO('JournalDAO'); 
		$contexts = $JournalDAO->getAll();
		$context = ($contexts->toArray())[0]; 

		$deployment = new CrossrefConferenceExportDeployment($context,$this->plugin);
		
		$doc = $this->doc;
		$crossRef = new PaperCrossrefXmlConferenceFilter($this->filterGroup);
		$crossRef->setDeployment($deployment);

		$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
		$submissions = $submissionDao->getByContextId(1);
		$submission = $submissions->toArray();

		$doc = $crossRef->process($submission);
		
		$doc->save($xml_file_name);

		self::assertTrue(is_file($xml_file_name));
	}

	private function getTestData() {
		$sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
		return file_get_contents($sampleFile);
	}

}

?>