<?php 

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.crossrefConference.filter.IssueCrossrefXmlConferenceFilter');
import('lib.pkp.classes.user.User');
require('lib/pkp/classes/context/Context.inc.php');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.crossrefConference.tests.ContextMock');


class IssueCrossrefXmlConferenceFilterTest extends PKPTestCase {

	public function testCreateRootNode(){
		$fileTest = '../crossrefConference/tests/conference-test.xml';
		$contentFile = file_get_contents($fileTest);

		$expectedDoiBatch =  new DOMDocument('1.0', 'utf-8');
		$expectedDoiBatch->loadXML($contentFile);

		$filterGroup = new FilterGroup();
		$context = new ContextMock();
		$user = new User();
		$deployment = new NativeImportExportDeployment($context,$user);

		$doc = new DOMDocument('1.0', 'utf-8');
		$crossRef = new IssueCrossrefXmlConferenceFilter($filterGroup);
		$crossRef->setDeployment($deployment);
        $doiBatch = $crossRef->createRootNode($doc);  
        $doc->appendChild($doiBatch);
        
		self::assertEquals(
			$expectedDoiBatch->getElementsByTagName("doi_batch"),
			$doiBatch->getElementsByTagName("doi_batch")
		);

	}

	function setDeployment($deployment) {
		$this->_deployment = $deployment;
	}

	function getDeployment() {
		return $this->_deployment;
	}
    
}
?>