<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\Security\Authentication\Infrastructure\Provider;

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Application\Configuration\User\Repository\WriteUserRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\Authentication\Infrastructure\Provider\SamlAuthFactoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter\SettingsFormatterInterface;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\Conditions;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\RolesMapping;
use DOMDocument;
use DOMElement;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * Regression coverage for MON-152191.
 *
 * The ticket symptom was that, at logout, Centreon emitted a <samlp:LogoutRequest>
 * whose <saml:NameID> was the IdP metadata URL with Format "...nameid-format:entity",
 * instead of the user login with the format negotiated at login. OneLogin rejected it
 * with: NameID '...metadata/' does not match prior auth request '<login>'.
 *
 * Root cause: the PHP session (holding samlNameId / samlNameIdFormat) was wiped before
 * SAML::logout() ran, so null identifiers reached OneLogin\Saml2\Auth::logout(). When the
 * nameId is empty, php-saml falls back to the IdP entityId + NAMEID_ENTITY format
 * (see vendor/onelogin/php-saml/src/Saml2/LogoutRequest.php).
 *
 * These tests assert the *content* of the LogoutRequest XML actually produced by a real
 * OneLogin\Saml2\Auth, driven through Centreon's real SAML::logout() code path. Only
 * Auth::redirectTo() is neutralised so the test does not exit the process; the request XML
 * is built for real and read back through Auth::getLastRequestXML().
 */
class SAMLLogoutRequestContentTest extends TestCase
{
    private const IDP_ENTITY_ID = 'https://sso.example.com/saml-idp/abc123/metadata/';
    private const USER_LOGIN = 'demo-yha9227';

    // Throwaway self-signed certificate; OneLogin\Saml2\Settings requires an IdP certificate in
    // strict mode, just as OneLoginSettingsFormatter always provides one from the stored config.
    private const IDP_X509_CERT = 'MIIDBzCCAe+gAwIBAgIUez2J1H/1/vfxpusApnam3Se8LYswDQYJKoZIhvcNAQEL'
        . 'BQAwEzERMA8GA1UEAwwIdGVzdC1pZHAwHhcNMjYwNTI5MDkzNDA2WhcNMzYwNTI2MDkzNDA2WjATMREwDwYD'
        . 'VQQDDAh0ZXN0LWlkcDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMidM4ZwIYa9etOUftZqMfaS'
        . 'HLlrYpsgknkKakm+U6qJrgSkNxcw+vsJIOgU/i0/wO5vBzvDGMHoh/kFqP/j6m0CuxIbBpIfWjFdcRdSNKd0'
        . 'Tdb6ZGWqcQRoX3IFvezfsIE+QG4MYofewVQLYjbcxfwWay/dtM/6bX9p7NIKPiSky0k1o9vnXZVvEusN9X6D'
        . 'wywH3JDxONzrqMrwnL4Nudid8qSKfPT2poHnOhl3OJ5EZxHiYGDlBlpqjA4Xnn0lFmcl62CFVPJcswDJm4/k'
        . 'CqF+ficeWgwCfrj0yTJhUmS7jgswo0RdN4UdAyZE/75UwJmyOi6roAuEkMsZCBkEJckCAwEAAaNTMFEwHQYD'
        . 'VR0OBBYEFFUMunxgNC2bKwg62Dr+MfGM0bYcMB8GA1UdIwQYMBaAFFUMunxgNC2bKwg62Dr+MfGM0bYcMA8G'
        . 'A1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBADWzZ3IEkxbscQS+SnAFDytZWR6llAl1c3YSjxMo'
        . '7emEuO87wXYukWr5AuzefTYu/OG584IUbAyM6jv8D9c6oDK4PHwP8Zekd7RabXvqAALmq682ezHIHUlhxZ/k'
        . 'i9rvu9FHwaIkrjXZzu4ZA+m4efYkRe1Wi5UCpBizVkVgNiwOOMPKgFadkCIK62nEwR938FU5oalfzAPjM8Do'
        . 'sGW05gQKclsOHWlMxb9UvN72jVY9wElXSjJJVEHBGUF6J2I0LPlIICGKrzoSy1hqDoJg9jZOxYLp7XGP8FL8'
        . 'hoRyMDdwKknaZzd6HJSn14Kxj1NpVdjdt0F1IYGWP4yZS6ZuQSA=';

    protected function tearDown(): void
    {
        unset($_SESSION['saml'], $_SESSION['LogoutRequestID']);
    }

    public function testLogoutRequestUsesUserLoginAndNegotiatedFormatWhenSessionIsPresent(): void
    {
        // The fixed flow: SAML::logout() runs *before* the session is invalidated, so the
        // identifiers stored at login are still available and forwarded to php-saml.
        $_SESSION['saml'] = [
            'samlNameId' => self::USER_LOGIN,
            'samlSessionIndex' => '_session-index-123',
            'samlNameIdFormat' => Constants::NAMEID_UNSPECIFIED,
            'samlNameIdNameQualifier' => null,
            'samlNameIdSPNameQualifier' => null,
        ];

        $auth = $this->createRealAuthWithoutRedirect();
        $this->createSaml($auth)->logout();

        $nameId = $this->extractNameIdElement($auth->getLastRequestXML());

        self::assertSame(
            self::USER_LOGIN,
            $nameId->textContent,
            'The LogoutRequest NameID must be the user login, not the IdP metadata URL.'
        );
        self::assertNotSame(
            Constants::NAMEID_ENTITY,
            $nameId->getAttribute('Format'),
            'The LogoutRequest NameID Format must not fall back to the "entity" format.'
        );
        // php-saml omits the Format attribute when it equals the (default) "unspecified" value.
        self::assertFalse(
            $nameId->hasAttribute('Format'),
            'A NameID forwarded with the "unspecified" format is emitted without a Format attribute.'
        );
    }

    public function testLogoutRequestFallsBackToIdpEntityWhenSessionWasWiped(): void
    {
        // Documents the original bug: with the session already wiped, no identifiers reach
        // php-saml and it falls back to the IdP entityId + entity format that OneLogin rejected.
        unset($_SESSION['saml']);

        $auth = $this->createRealAuthWithoutRedirect();
        $this->createSaml($auth)->logout();

        $nameId = $this->extractNameIdElement($auth->getLastRequestXML());

        self::assertSame(
            self::IDP_ENTITY_ID,
            $nameId->textContent,
            'Without session identifiers, php-saml emits the IdP entityId as NameID (the bug).'
        );
        self::assertSame(
            Constants::NAMEID_ENTITY,
            $nameId->getAttribute('Format'),
            'Without session identifiers, php-saml emits the entity NameID format (the bug).'
        );
    }

    /**
     * Builds a real OneLogin\Saml2\Auth (real Settings, real LogoutRequest generation) and
     * neutralises only redirectTo() so the request is built but the process is not terminated.
     */
    private function createRealAuthWithoutRedirect(): Auth
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->setConstructorArgs([$this->oneLoginSettings()])
            ->onlyMethods(['redirectTo'])
            ->getMock();
        $auth->method('redirectTo')->willReturn('');

        return $auth;
    }

    /**
     * Minimal settings matching the shape produced by OneLoginSettingsFormatter::format(),
     * with the IdP entityId set to a metadata URL like the one reported in MON-152191.
     *
     * @return array<string,mixed>
     */
    private function oneLoginSettings(): array
    {
        return [
            'strict' => true,
            'sp' => [
                'entityId' => 'https://centreon.example.com/centreon/api/latest/saml/metadata',
                'assertionConsumerService' => [
                    'url' => 'https://centreon.example.com/centreon/api/latest/saml/acs',
                ],
            ],
            'idp' => [
                'entityId' => self::IDP_ENTITY_ID,
                'singleSignOnService' => [
                    'url' => 'https://sso.example.com/saml-idp/abc123/sso',
                ],
                'singleLogoutService' => [
                    'url' => 'https://sso.example.com/saml-idp/abc123/slo',
                ],
                'x509cert' => self::IDP_X509_CERT,
            ],
        ];
    }

    private function createSaml(Auth $auth): SAML
    {
        $formatter = $this->createMock(SettingsFormatterInterface::class);
        $formatter->method('format')->willReturn([]);

        $authFactory = $this->createMock(SamlAuthFactoryInterface::class);
        $authFactory->method('create')->willReturn($auth);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomConfiguration')
            ->willReturn($this->createMock(CustomConfigurationInterface::class));

        $saml = new SAML(
            $this->createMock(Container::class),
            $this->createMock(ContactRepositoryInterface::class),
            $this->createMock(LoginLoggerInterface::class),
            $this->createMock(WriteUserRepositoryInterface::class),
            $this->createMock(Conditions::class),
            $this->createMock(RolesMapping::class),
            $this->createMock(GroupsMapping::class),
            $formatter,
            $this->createMock(WriteSessionRepositoryInterface::class),
            $authFactory,
        );
        $saml->setConfiguration($configuration);

        return $saml;
    }

    private function extractNameIdElement(?string $logoutRequestXml): DOMElement
    {
        self::assertNotEmpty($logoutRequestXml, 'No LogoutRequest XML was produced.');

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($logoutRequestXml), 'The LogoutRequest XML is not parseable.');

        $nameIds = $document->getElementsByTagNameNS(
            'urn:oasis:names:tc:SAML:2.0:assertion',
            'NameID'
        );
        self::assertSame(1, $nameIds->count(), 'Exactly one <saml:NameID> is expected in the LogoutRequest.');

        $nameId = $nameIds->item(0);
        self::assertInstanceOf(DOMElement::class, $nameId);

        return $nameId;
    }
}
