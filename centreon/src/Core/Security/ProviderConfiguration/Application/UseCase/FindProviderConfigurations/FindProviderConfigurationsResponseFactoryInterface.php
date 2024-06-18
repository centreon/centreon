<?php

namespace Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations;

use Core\Security\ProviderConfiguration\Domain\Model\Configuration;

interface FindProviderConfigurationsResponseFactoryInterface
{
    public function createResponse(Configuration): FindProviderConfigurationsResponse;
}