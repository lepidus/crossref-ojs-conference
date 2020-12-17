<?php 

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');
import('lib.pkp.classes.user.User');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.crossrefConference.tests.ContextMock');
import('plugins.importexport.crossrefConference.CrossrefExportConferenceDeployment');
echo("");
echo("CROSSREFCONFERENCE\n");

$expectedDoiBatch = new DOMDocument('1.0', 'utf-8');
$expectedDoiBatch->loadXML(getTestData());
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
$expected = $elements->childNodes[1];
print_r($expected);

// $doiBatch->appendChild($crossRef->createHeadNode($doc));



function getTestData() {
	$sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
	return file_get_contents($sampleFile);
}

class IssueCrossrefXmlConferenceFilterTest extends PKPTestCase {

	private $expectedFile;
	private $doc; 

	protected function setUp() : void {
		$this->expectedFile = new DOMDocument('1.0', 'utf-8');
		$this->expectedFile->loadXML($this->getTestData());
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
		
		self::assertEquals(
			$this->expectedFile->getElementsByTagName("doi_batch")->item(0),
			$this->doc->getElementsByTagName("doi_batch")->item(0),
			"actual xml is equal to expected xml"
		);
	}
	
	
	public function testCreateHeadNode(){

		$filterGroup = new FilterGroup();

		$context = new ContextMock();
		$user = new User();
		$deployment = new CrossrefExportConferenceDeployment($context,$user);

		$doc = $this->doc; 
		//$doc->formatOutput = true;
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);

		$head = $crossRef->createHeadNode($doc);
		$head = $doc->appendChild($head);
		// echo $doc->saveXML() . "\n";

		$elements = $this->expectedFile->getElementsByTagName('doi_batch')->item(0);
		$expected = $elements->childNodes[1];
		
		self::assertEquals(
			$expected,
			$this->doc->getElementsByTagName("head")->item(0),
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