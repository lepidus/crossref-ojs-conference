<?php 

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');
import('lib.pkp.classes.user.User');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.crossrefConference.tests.ContextMock');
import('plugins.importexport.crossrefConference.CrossrefExportConferenceDeployment');
echo("");
echo("CROSSREFCONFERENCE\n");

/*
$expectedDoiBatch = new DOMDocument('1.0', 'utf-8');
$expectedDoiBatch->loadXML(getTestData());
print_r($expectedDoiBatch->getElementsByTagName("doi_batch")[0]);

echo("\n");
*/

/*
$filterGroup = new FilterGroup();
$context = new ContextMock();
$user = new User();
$deployment = new NativeImportExportDeployment($context,$user);


$doc = new DOMDocument('1.0', 'utf-8');
$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
$crossRef->setDeployment($deployment);
$doiBatch = $crossRef->createRootNode($doc);
$doc->appendChild($doiBatch);

print_r($doc->getElementsByTagName("doi_batch")[0]);


function getTestData() {
	$sampleFile = '../crossrefConference/tests/conference-test.xml';
	return file_get_contents($sampleFile);
}

*/
class IssueCrossrefXmlConferenceFilterTest extends PKPTestCase {


	public function testCreateRootNode(){
		
		$expectedDoiBatch = new DOMDocument('1.0', 'utf-8');
		$expectedDoiBatch->loadXML($this->getTestData());

		$filterGroup = new FilterGroup();
		$context = new ContextMock();
		$user = new User();
		$deployment = new CrossrefExportConferenceDeployment($context,$user);
		

		$doc = new DOMDocument('1.0', 'utf-8');
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);
        $doiBatch = $crossRef->createRootNode($doc);
        $doc->appendChild($doiBatch);

		self::assertEquals(
			$expectedDoiBatch->getElementsByTagName("doi_batch")[0],
			$doc->getElementsByTagName("doi_batch")[0],
			"actual xml is equal to expected xml"
		);
	}

	private function getTestData() {
		$sampleFile = '../crossrefConference/tests/conference-test.xml';
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