<?php

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\Factory;

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\FindProviderConfigurationsResponse;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\FindProviderConfigurationsResponseFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\OpenIdConfigurationException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OpenIdProviderResponseFactory implements FindProviderConfigurationsResponseFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository
    ) {

    }

    public function isValidFor(string $type): bool
    {
        return $type === Provider::OPENID;
    }

    /**
     * @param Configuration $configuration
     *
     * @throws OpenIdConfigurationException
     * @throws \Throwable
     *
     * @return FindProviderConfigurationsResponse
     */
    public function createResponse(Configuration $configuration): FindProviderConfigurationsResponse
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();
        $response = new FindProviderConfigurationsResponse();
        $response->id = $configuration->getId();
        $response->type = $configuration->getType();
        $response->name = $configuration->getName();
        $response->authenticationUri = $this->buildAuthenticationUri($customConfiguration);
        $response->isActive = $configuration->isActive();
        $response->isForced = $configuration->isForced();

        return $response;
    }

    /**
     * @param CustomConfiguration $customConfiguration
     *
     * @throws OpenIdConfigurationException
     * @throws \Throwable
     *
     * @return string
     *
     */
    private function buildAuthenticationUri(CustomConfiguration $customConfiguration): string
    {
        $redirectUri = $customConfiguration->getRedirectUrl() !== null
            ? $response->redirectUrl . $this->urlGenerator->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            : $this->urlGenerator->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        if (
            $customConfiguration->getBaseUrl() === null
            || $customConfiguration->getClientId() === null
            || $customConfiguration->getAuthorizationEndpoint() === null
        ) {
            return '';
        }

        if (
            $this->readVaultConfigurationRepository->exists()
            && str_starts_with($customConfiguration->getClientId(), 'secret::')
        ) {
            $openIDCredentialsFromVault = $this->readVaultRepository->findFromPath($customConfiguration->getClientId());
            if (! array_key_exists('_OPENID_CLIENT_ID', $openIDCredentialsFromVault)) {
                throw OpenIdConfigurationException::unableToRetrieveCredentialsFromVault(['_OPENID_CLIENT_ID']);
            }
            $customConfiguration->setClientId($openIDCredentialsFromVault['_OPENID_CLIENT_ID']);
        }

        $authenticationUriParts = [
            'client_id' => $customConfiguration->getClientId(),
            'response_type' => 'code',
            'redirect_uri' => rtrim($redirectUri, '/'),
            'state' => uniqid(),
        ];

        $authorizationEndpointBase = parse_url(
            $customConfiguration->getAuthorizationEndpoint(),
            PHP_URL_PATH
        );
        $authorizationEndpointParts = parse_url(
            $customConfiguration->getAuthorizationEndpoint(),
            PHP_URL_QUERY
        );
        if ($authorizationEndpointBase === false || $authorizationEndpointParts === false) {
            throw new \ValueError(_('Unable to parse authorization url'));
        }

        $queryParams = http_build_query($authenticationUriParts);
        if ($authorizationEndpointParts !== null) {
            $queryParams .= '&' . $authorizationEndpointParts;
        }

        return $customConfiguration->getBaseUrl() . '/' .
            ltrim($authorizationEndpointBase ?? '', '/')
            . '?' . $queryParams
            . (! empty($customConfiguration->getConnectionScopes())
                ? '&scope=' . implode('%20', $customConfiguration->getConnectionScopes())
                : ''
            );
    }
}