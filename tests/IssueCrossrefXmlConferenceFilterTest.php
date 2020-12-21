<?php 

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');
import('lib.pkp.classes.user.User');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.crossrefConference.tests.ContextMock');
import('plugins.importexport.crossrefConference.tests.PluginMock');
import('plugins.importexport.crossrefConference.CrossrefExportConferenceDeployment');
import("classes.submission.Submission");
echo("");
echo("CROSSREFCONFERENCE\n");

/*
$expectedDoiBatch = new DOMDocument('1.0', 'utf-8');
$expectedDoiBatch->loadXML(getTestData());
$expectedDoiBatch->formatOutput = true;
//print_r(getTestData());
$filterGroup = new FilterGroup();

$context = new ContextMock();
$user = new User();
$deployment = new CrossrefExportConferenceDeployment($context,$user);

$doc = new DOMDocument('1.0', 'utf-8');
//$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
$crossRef->setDeployment($deployment);
$doiBatch = $crossRef->createRootNode($doc);
$doiBatch = $doc->appendChild($doiBatch);
$head = $crossRef->createHeadNode($doc);
$head = $doiBatch->appendChild($head);
//echo $doc->saveXML() . "\n";
$elements = $expectedDoiBatch->getElementsByTagName('doi_batch')->item(0);
$expected = $elements->childNodes[0];
echo $expectedDoiBatch->saveXML($expected);

//print_r($doc->getContext());

// $doiBatch->appendChild($crossRef->createHeadNode($doc));



function getTestData() {
	$sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
	return file_get_contents($sampleFile);
}
*/
class IssueCrossrefXmlConferenceFilterTest extends PKPTestCase {

	private $expectedFile;
	private $doc; 

	protected function setUp() : void {
		$this->expectedFile = new DOMDocument('1.0', 'utf-8');
		$this->expectedFile->loadXML($this->getTestData());
		$this->expectedFile->preserveWhiteSpace = false;
		$this->doc = new DOMDocument('1.0', 'utf-8');
		parent::setUp();
	}

	public function testCreateRootNode(){

		$filterGroup = new FilterGroup();
		
		$context = new ContextMock();
		$user = new User();
		$deployment = new CrossrefExportConferenceDeployment($context,$user);
		
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);
        $doiBatch = $crossRef->createRootNode($this->doc);
        $this->doc->appendChild($doiBatch);
		
		$elements = $this->expectedFile->documentElement;
		$elementHead = $elements->getElementsByTagName("head")->item(0);
		$removeNode = $elements->removeChild($elementHead);
		$elementBody = $elements->getElementsByTagName("body")->item(0);
		$removeNode = $elements->removeChild($elementBody);

		$doiBatchNode = $this->expectedFile->getElementsByTagName("doi_batch")->item(0);

		$actual = $this->doc->getElementsByTagName("doi_batch")->item(0);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($doiBatchNode),
			$this->doc->saveXML($actual),
			"actual xml is equal to expected xml"
		);
	}
	
	
	public function testCreateHeadNode(){

		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$plugin = new PluginMock();
		$deployment = new CrossrefExportConferenceDeployment($context,$user);
		$deployment->setPlugin($plugin);

		$doc = $this->doc; 
		$doc->formatOutput = true;
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$head = $crossRef->createHeadNode($doc);
		$head = $doc->appendChild($head);

		$elements = $this->expectedFile->getElementsByTagName('doi_batch')->item(0);
		$expected = $elements->childNodes[0];
		
		$actual = $this->doc->getElementsByTagName("head")->item(0);

		self::assertXmlStringEqualsXmlString(
			$this->expectedFile->saveXML($expected),
			$this->doc->saveXML($actual),
			"actual xml is equal to expected xml"
		);
		
	}
	
	public function testCreateConferenceNode(){

		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$plugin = new PluginMock();
		$deployment = new CrossrefExportConferenceDeployment($context,$user);
		$deployment->setPlugin($plugin);

		$doc = $this->doc; 
		$doc->formatOutput = true;
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$submission = new Submission();
		$bodyNode = $doc->createElement('body');
		$conference = $crossRef->createConferenceNode($doc, $submission);
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

	private function getTestData() {
		$sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
		return file_get_contents($sampleFile);
	}

	function setDeployment($deployment) {
		$this->_deployment = $deployment;
	}

	function getDeployment() {
		return $this->_deployment;
	}
    
}

?>