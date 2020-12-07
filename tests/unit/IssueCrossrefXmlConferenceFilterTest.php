<?php declare(strict_types=1);

require( '../ojs-3.2.1-1/tools/bootstrap.inc.php');


//define('DIR','../ojs-3.2.1-1/lib/pkp/plugins/importexport/native/filter/');
//include DIR . 'NativeExportFilter.inc.php';
//include DIR . 'NativeImportExportFilter.inc.php';

use PHPUnit\Framework\TestCase;

class IssueCrossrefXmlConferenceFilterTest extends TestCase {
    
    public function testCreateRootNode(){

		$expectedDoiBatch =  new DOMDocument();
		$expectedDoiBatch->load('../crossrefConference/tests/unit/conference-test.xml');

		$filterGroup = new FilterGroup();

		$doc = new DOMDocument('1.0', 'utf-8');
		$crossRef = new IssueCrossrefXmlFilter();
        $doiBatch = $crossref->createRootNode($doc);  
        $doc->appendChild($doiBatch);
        
		self::assertEquals(
			$expectedDoiBatch->getElementsByTagName("doi_batch"),
			$doiBatch->getElementsByTagName("doi_batch"),
			"Error while evaluating doi_batch"
		);

	} 
    
}
?>