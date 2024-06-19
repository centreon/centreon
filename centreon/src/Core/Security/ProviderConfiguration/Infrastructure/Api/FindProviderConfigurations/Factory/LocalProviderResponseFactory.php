<?php

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\Factory;

use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\FindProviderConfigurationsResponse;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\FindProviderConfigurationsResponseFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocalProviderResponseFactory implements FindProviderConfigurationsResponseFactoryInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function isValidFor(string $type): bool
    {
        return $type === Provider::LOCAL;
    }

    public function createResponse(Configuration $configuration): FindProviderConfigurationsResponse
    {
        $response = new FindProviderConfigurationsResponse();
        $response->id = $configuration->getId();
        $response->type = $configuration->getType();
        $response->name = $configuration->getName();
        $response->authenticationUri = $this->urlGenerator->generate(
            'centreon_security_authentication_login',
            ['providerName' => Provider::LOCAL]
        );
        $response->isActive = $configuration->isActive();
        $response->isForced = $configuration->isForced();

        return $response;
    }
}