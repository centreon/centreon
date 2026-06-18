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

namespace Tests\Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter;

use Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter\OneLoginSettingsFormatter;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\RequestedAuthnContextComparisonEnum;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Maps the stored SAML configuration to the settings array consumed by OneLogin\Saml2\Auth.
 *
 * The IdP entityId mapping is what made MON-152191 visible: at logout, when no session NameID
 * is available, php-saml falls back to idp.entityId as the <saml:NameID>. This test pins down
 * that idp.entityId is the configured metadata URL (and that the SLO URL is forwarded), so the
 * value the LogoutRequest falls back to is exactly what the configuration declares.
 */
class OneLoginSettingsFormatterTest extends TestCase
{
    private const IDP_ENTITY_ID = 'https://sso.example.com/saml-idp/abc123/metadata/';
    private const IDP_SSO_URL = 'https://sso.example.com/saml-idp/abc123/sso';
    private const IDP_SLO_URL = 'https://sso.example.com/saml-idp/abc123/slo';
    private const IDP_CERT = 'MIID-fake-certificate-body';
    private const ACS_URL = 'https://centreon.example.com/centreon/api/latest/saml/acs';

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private OneLoginSettingsFormatter $formatter;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')
            ->with('centreon_application_authentication_saml_acs', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn(self::ACS_URL);

        $this->formatter = new OneLoginSettingsFormatter($this->urlGenerator);

        // OneLoginSettingsFormatter resolves the SP entityId from the current request host
        // (HttpUrlTrait::getHost(), which reads the HTTP_HOST/REQUEST_SCHEME server variables).
        $_SERVER['HTTP_HOST'] = 'centreon.example.com';
        $_SERVER['REQUEST_SCHEME'] = 'https';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_SCHEME']);
    }

    public function testFormatMapsStoredConfigurationToOneLoginSettings(): void
    {
        $settings = $this->formatter->format($this->customConfiguration());

        self::assertSame(
            [
                'strict' => true,
                'debug' => true,
                'sp' => [
                    'entityId' => 'https://centreon.example.com',
                    'assertionConsumerService' => [
                        'url' => self::ACS_URL,
                    ],
                ],
                'idp' => [
                    'entityId' => self::IDP_ENTITY_ID,
                    'singleSignOnService' => [
                        'url' => self::IDP_SSO_URL,
                    ],
                    'singleLogoutService' => [
                        'url' => self::IDP_SLO_URL,
                    ],
                    'x509cert' => self::IDP_CERT,
                ],
                'security' => [
                    'requestedAuthnContext' => true,
                    'requestedAuthnContextComparison' => 'minimum',
                ],
            ],
            $settings
        );
    }

    public function testFormatForwardsTheIdpEntityIdAndLogoutUrlUsedByTheLogoutRequest(): void
    {
        $settings = $this->formatter->format($this->customConfiguration());

        // idp.entityId is the value php-saml emits as NameID when no session NameID is present
        // (the MON-152191 fallback); it must be the configured metadata URL.
        self::assertSame(self::IDP_ENTITY_ID, $settings['idp']['entityId']);
        // Without a Single Logout URL, OneLogin\Saml2\Auth::logout() throws and no SLO is sent.
        self::assertSame(self::IDP_SLO_URL, $settings['idp']['singleLogoutService']['url']);
    }

    /**
     * CustomConfiguration is final with a private constructor; build a real instance without the
     * constructor and set only the fields the formatter reads, through its public setters.
     */
    private function customConfiguration(): CustomConfiguration
    {
        $customConfiguration = (new ReflectionClass(CustomConfiguration::class))->newInstanceWithoutConstructor();
        $customConfiguration->setEntityIDUrl(self::IDP_ENTITY_ID);
        $customConfiguration->setRemoteLoginUrl(self::IDP_SSO_URL);
        $customConfiguration->setLogoutFromUrl(self::IDP_SLO_URL);
        $customConfiguration->setPublicCertificate(self::IDP_CERT);
        $customConfiguration->setRequestedAuthnContext(true);
        $customConfiguration->setRequestedAuthnContextComparison(RequestedAuthnContextComparisonEnum::MINIMUM);

        return $customConfiguration;
    }
}
