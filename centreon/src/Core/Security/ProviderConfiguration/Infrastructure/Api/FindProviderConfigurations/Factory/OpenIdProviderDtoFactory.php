<?php declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\Factory;

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDto;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDtoFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\OpenIdConfigurationException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OpenIdProviderDtoFactory implements ProviderConfigurationDtoFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository
    ) {

    }

    /**
     * @inheritDoc
     */
    public function supports(string $type): bool
    {
        return $type === Provider::OPENID;
    }

    /**
     * @inheritDoc
     */
    public function createResponse(Configuration $configuration): ProviderConfigurationDto
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();
        $dto = new ProviderConfigurationDto();
        $dto->id = $configuration->getId();
        $dto->type = $configuration->getType();
        $dto->name = $configuration->getName();
        $dto->authenticationUri = $this->buildAuthenticationUri($customConfiguration);
        $dto->isActive = $configuration->isActive();
        $dto->isForced = $configuration->isForced();

        return $dto;
    }

    /**
     * @param CustomConfiguration $customConfiguration
     *
     * @throws OpenIdConfigurationException
     * @throws \Throwable
     *
     * @return string
     */
    private function buildAuthenticationUri(CustomConfiguration $customConfiguration): string
    {
        $redirectUri = $customConfiguration->getRedirectUrl() !== null
            ? $customConfiguration->getRedirectUrl() . $this->urlGenerator->generate(
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
                throw OpenIdConfigurationException::unableToRetrieveCredentialFromVault('_OPENID_CLIENT_ID');
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

        return $customConfiguration->getBaseUrl() . '/'
            . ltrim($authorizationEndpointBase ?? '', '/')
            . '?' . $queryParams
            . (! empty($customConfiguration->getConnectionScopes())
                ? '&scope=' . implode('%20', $customConfiguration->getConnectionScopes())
                : ''
            );
    }
}