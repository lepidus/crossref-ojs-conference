<?php 

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');
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
echo("");

/*

$filterGroup = new FilterGroup();

$JournalDAO =& DAORegistry::getDAO('JournalDAO'); 
$contexts = $JournalDAO->getAll();

$plugin = new PluginMock();
echo("KKKKKKKKKKKKK");
print_r(($contexts->toArray())[0]);

 $deployment = new CrossrefConferenceExportDeployment($context,$plugin);
$deployment->setPlugin($plugins);

$doc = new DOMDocument('1.0', 'utf-8');
$crossRef = new PaperCrossrefXmlConferenceFilter($filterGroup);
$crossRef->setDeployment($deployment);

$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
$submissions = $submissionDao->getByContextId(1);
$submission = $submissions->toArray();

$doc = $crossRef->process($submission); */

//echo $doc->saveXML();



class PaperCrossrefXmlConferenceFilterTest extends PKPTestCase {

	private $expectedFile;
	private $doc; 

	protected function setUp() : void {
		$this->expectedFile = new DOMDocument('1.0', 'utf-8');
		$this->expectedFile->loadXML($this->getTestData());
		$this->expectedFile->preserveWhiteSpace = false;
		$this->doc = new DOMDocument('1.0', 'utf-8');
		parent::setUp();
	}

	public function testCreateConferenceNode(){

		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$plugin = new PluginMock();
		$deployment = new CrossrefConferenceExportDeployment($context,$user);
		$deployment->setPlugin($plugin);

		$doc = $this->doc; 
		$doc->formatOutput = true;
		$crossRef = new PaperCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
		$submissions = $submissionDao->getByContextId(1);
		$submission = $submissions->toArray();
		
		$bodyNode = $doc->createElement('body');
		$conference = $crossRef->createConferenceNode($doc, $submission[0]);
		$bodyNode->appendChild($conference);
		$doc->appendChild($bodyNode);

		$elements = $this->expectedFile->getElementsByTagName('doi_batch')->item(0);
		$expected = $elements->childNodes[1];
		
		$actual = $this->doc->getElementsByTagName("body")->item(0);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expected),
			$this->doc->saveXML($actual),
			"actual xml is equal to expected xml"
		);
		
	}

	public function testCreateXML(){
		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$plugin = new PluginMock();
		$deployment = new CrossrefConferenceExportDeployment($context,$user);
		$deployment->setPlugin($plugin);

		$doc = $this->doc; 
		$doc->formatOutput = true;
		$crossRef = new PaperCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);
		
		$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
		$submissions = $submissionDao->getByContextId(1);
		$submission = $submissions->toArray();

		$conference = $crossRef->process($submission);
		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML(),
			$conference->saveXML(),
			"actual xml is equal to expected xml"
		);
		
	}

	public function testGenerateXMLFile(){

		$xml_file_name = './plugins/importexport/crossrefConference/tests/conferencia-teste.xml';

		$filterGroup = new FilterGroup();

		$JournalDAO =& DAORegistry::getDAO('JournalDAO'); 
		$contexts = $JournalDAO->getAll();
		$context = ($contexts->toArray())[0]; 
		
		$plugin = new PluginMock();

		$deployment = new CrossrefConferenceExportDeployment($context,$plugin);

		$doc = $this->doc;
		$crossRef = new PaperCrossrefXmlConferenceFilter($filterGroup);
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