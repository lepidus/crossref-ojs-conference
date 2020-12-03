<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class IssueCrossrefXmlConferenceFilterTest extends TestCase {
    
    public function testCreateRootNode(){

		$expectedDoiBatch =  new DOMDocument();
        $expectedDoiBatch->load('tests/unit/conference-test.xml');

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