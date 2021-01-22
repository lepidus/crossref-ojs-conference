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

// $expectedFile = new DOMDocument('1.0', 'utf-8');
// $expectedFile->preserveWhiteSpace = true;
// $doc = new DOMDocument('1.0', 'utf-8');

// $expectedFile->loadXML(file_get_contents('./plugins/importexport/crossrefConference/tests/headConference-test.xml'));

// $filterGroup = new FilterGroup();

// $context = new ContextMock();
// $user = new User();
// $plugin = new PluginMock();
// $deployment = new CrossrefConferenceExportDeployment($context,$user);
// $deployment->setPlugin($plugin);


// $doc->formatOutput = true;
// $crossRef = new ProceedingsCrossrefXmlConferenceFilter($filterGroup);
// $crossRef->setDeployment($deployment);

// $head = $crossRef->createHeadNode($doc);
// $head = $doc->appendChild($head);

// $expected = $expectedFile->getElementsByTagName('head')->item(0);
// $childDoiBatch = $expected->getElementsByTagName('doi_batch_id')->item(0);
// $childDoiBatch->textContent = 'proceeding_' . time();

// $actual = $doc->getElementsByTagName("head")->item(0);
// $testActual = $actual->getElementsByTagName('doi_batch_id')->item(0);
// $testActual->textContent = 'proceeding_' . time();

// echo $expectedFile->saveXML($childDoiBatch) . '\n';
// echo $doc->saveXML($testActual);


class ProceedingsCrossrefXmlConferenceFilterTest extends PKPTestCase {

	private $expectedFile;
	private $doc; 

	protected function setUp() : void {
		$this->expectedFile = new DOMDocument('1.0', 'utf-8');
		$this->expectedFile->preserveWhiteSpace = true;
		//$this->expectedFile->loadXML($this->getTestData());
		$this->doc = new DOMDocument('1.0', 'utf-8');
		parent::setUp();
	}

	public function testCreateRootNode(){

		$this->expectedFile->loadXML(file_get_contents('./plugins/importexport/crossrefConference/tests/rootConference-test.xml'));

		$filterGroup = new FilterGroup();
		
		$context = new ContextMock();
		$user = new User();
		$deployment = new CrossrefConferenceExportDeployment($context,$user);
		
		$crossRef = new ProceedingsCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);
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
	
	
	public function testCreateHeadNode(){

		$this->expectedFile->loadXML(file_get_contents('./plugins/importexport/crossrefConference/tests/headConference-test.xml'));

		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$plugin = new PluginMock();
		$deployment = new CrossrefConferenceExportDeployment($context,$user);
		$deployment->setPlugin($plugin);


		$doc = $this->doc; 
		$doc->formatOutput = true;
		$crossRef = new ProceedingsCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$head = $crossRef->createHeadNode($doc);
		$head = $doc->appendChild($head);

		$expected = $this->expectedFile->getElementsByTagName('head')->item(0);

		$expectedDoiBatch = $expected->getElementsByTagName('doi_batch_id')->item(0);
		$expectedDoiBatch->textContent = 'proceeding_' . time();

		$expectedTimeStamp =  $expected->getElementsByTagName('timestamp')->item(0);
		$expectedTimeStamp->textContent = time();

		$expectedDepositor =  $expected->getElementsByTagName('depositor')->item(0);

		$expectedRegistrant =  $expected->getElementsByTagName('registrant')->item(0);


		$actual = $this->doc->getElementsByTagName("head")->item(0);

		$actualDoiBatch = $actual->getElementsByTagName('doi_batch_id')->item(0);
		$actualDoiBatch->textContent = 'proceeding_' . time();

		$actualTimeStamp =  $actual->getElementsByTagName('timestamp')->item(0);
		$actualTimeStamp->textContent = time();

		$actualDepositor =  $actual->getElementsByTagName('depositor')->item(0);
		$actualDepositorName = $actualDepositor->getElementsByTagName('name')->item(0);
		$actualDepositorName->textContent = 'Lepidus Tecnologia';
		$actualDepositorEmail = $actualDepositor->getElementsByTagName('email_address')->item(0);
		$actualDepositorEmail->textContent = 'doi@lepidus.com.br';

		$actualRegistrant =  $actual->getElementsByTagName('registrant')->item(0);
		$actualRegistrant->textContent = 'SBMAC';


		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expectedDoiBatch),
			$this->doc->saveXML($actualDoiBatch)	
		);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expectedTimeStamp),
			$this->doc->saveXML($actualTimeStamp)	
		);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expectedDepositor),
			$this->doc->saveXML($actualDepositor)	
		);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expectedRegistrant),
			$this->doc->saveXML($actualRegistrant)	
		);
		
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
		$crossRef = new ProceedingsCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$issue = new Issue();
		$issue->setDatePublished(date("Y/m/d"));

		$submission = new Submission();

		$bodyNode = $doc->createElement('body');
		$conference = $crossRef->createConferenceNode($doc, $issue);
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

	private function getRootTestData() {
		$sampleFile = './plugins/importexport/crossrefConference/tests/rootConference-test.xml';
		return file_get_contents($sampleFile);
	}
    
}

?>