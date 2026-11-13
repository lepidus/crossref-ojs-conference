<?php

use APP\plugins\generic\crossrefConference\classes\APIKeyEncryption;
use PKP\tests\PKPTestCase;

class APIKeyEncryptionTest extends PKPTestCase
{
    private const SECRET = 'a-secret-for-the-unit-tests';

    private APIKeyEncryption $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryption = new APIKeyEncryption(self::SECRET);
    }

    public function testReportsWhetherASecretIsConfigured(): void
    {
        $this->assertTrue($this->encryption->secretConfigExists());
        $this->assertFalse((new APIKeyEncryption(''))->secretConfigExists());
    }

    public function testRefusesToEncryptWithoutASecret(): void
    {
        $this->expectException(Exception::class);
        (new APIKeyEncryption(''))->encryptString('crossref-password');
    }

    public function testRecoversThePasswordItEncrypted(): void
    {
        $encrypted = $this->encryption->encryptString('crossref-password');

        $this->assertStringStartsWith('base64:', $encrypted);
        $this->assertStringNotContainsString('crossref-password', $encrypted);
        $this->assertSame('crossref-password', $this->encryption->decryptString($encrypted));
    }

    public function testEachEncryptionProducesADifferentCiphertext(): void
    {
        $this->assertNotSame($this->encryption->encryptString('crossref-password'), $this->encryption->encryptString('crossref-password'));
    }

    public function testRecognizesOnlyTextItCanDecrypt(): void
    {
        $encrypted = $this->encryption->encryptString('crossref-password');

        $this->assertTrue($this->encryption->textIsEncrypted($encrypted));
        $this->assertFalse($this->encryption->textIsEncrypted('crossref-password'));
        $this->assertFalse($this->encryption->textIsEncrypted(''));
        $this->assertFalse($this->encryption->textIsEncrypted('base64:not-encrypted'));
        $this->assertFalse((new APIKeyEncryption('another-secret'))->textIsEncrypted($encrypted));
    }
}
