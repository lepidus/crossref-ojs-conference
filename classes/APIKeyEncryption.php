<?php

namespace APP\plugins\generic\crossrefConference\classes;

use Exception;
use Illuminate\Encryption\Encrypter;
use PKP\config\Config;

class APIKeyEncryption
{
    private const ENCRYPTION_CIPHER = 'aes-256-cbc';
    private const BASE64_PREFIX = 'base64:';

    public function __construct(private ?string $secret = null)
    {
    }

    public function secretConfigExists(): bool
    {
        try {
            $this->getSecret();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private function getSecret(): string
    {
        $secret = (string) ($this->secret ?? Config::getVar('security', 'api_key_secret'));
        if ($secret === '') {
            throw new Exception("Crossref Conference Plugin - A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted");
        }

        return hash('sha256', $secret, true);
    }

    public function textIsEncrypted(string $text): bool
    {
        if (!str_starts_with($text, self::BASE64_PREFIX)) {
            return false;
        }

        try {
            $this->decryptString($text);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function encryptString(
        #[\SensitiveParameter]
        string $plainText
    ): string {
        $encrypter = new Encrypter($this->getSecret(), self::ENCRYPTION_CIPHER);

        try {
            $encryptedString = $encrypter->encrypt($plainText);
        } catch (Exception $e) {
            throw new Exception('Crossref Conference Plugin - Failed to encrypt string');
        }

        return self::BASE64_PREFIX . base64_encode($encryptedString);
    }

    public function decryptString(string $encryptedText): string
    {
        $encrypter = new Encrypter($this->getSecret(), self::ENCRYPTION_CIPHER);
        $payload = base64_decode(substr($encryptedText, strlen(self::BASE64_PREFIX)));

        try {
            return $encrypter->decrypt($payload);
        } catch (Exception $e) {
            throw new Exception('Crossref Conference Plugin - Failed to decrypt string');
        }
    }
}
