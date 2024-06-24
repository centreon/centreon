<?php declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\Factory;

use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDto;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDtoFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocalProviderDtoFactory implements ProviderConfigurationDtoFactoryInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @inheritDoc
     */
    public function supports(string $type): bool
    {
        return $type === Provider::LOCAL;
    }

    /**
     * @inheritDoc
     */
    public function createResponse(Configuration $configuration): ProviderConfigurationDto
    {
        $dto = new ProviderConfigurationDto();
        $dto->id = $configuration->getId();
        $dto->type = $configuration->getType();
        $dto->name = $configuration->getName();
        $dto->authenticationUri = $this->urlGenerator->generate(
            'centreon_security_authentication_login',
            ['providerName' => Provider::LOCAL]
        );
        $dto->isActive = $configuration->isActive();
        $dto->isForced = $configuration->isForced();

        return $dto;
    }
}