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
echo("");

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

		$issue = new Issue();
		$issue->setDatePublished(date("Y/m/d"));
		/*
		$submissionDao =& DAORegistry::getDAO('SubmissionDAO'); 
		$publicationDao =& DAORegistry::getDAO('PublicationDAO');

		$submission = new Submission();
		$submissionId = $submission->getData('id');

		$publication = new Publication();
		$publication->setData('submissionId',$submissionId);
		$publication->setData('locale', 'pt_BR');

		$submissionDao->insertObject($submission);
		$publicationDao->insertObject($publication);
		*/
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

		$issue = new Issue();
		$issue->setDatePublished(date("Y/m/d"));
		//$issues = array($issue);
		
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

	private function getTestData() {
		$sampleFile = './plugins/importexport/crossrefConference/tests/conference-test.xml';
		return file_get_contents($sampleFile);
	}

}

?>