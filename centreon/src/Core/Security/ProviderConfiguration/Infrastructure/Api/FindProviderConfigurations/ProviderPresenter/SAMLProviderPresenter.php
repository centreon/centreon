<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\ProviderPresenter;

use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderResponse\{
    SAMLProviderResponse};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SAMLProviderPresenter implements ProviderPresenterInterface
{
    /**
     * @param UrlGeneratorInterface $router
     */
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(mixed $response): bool
    {
        return is_a($response, SAMLProviderResponse::class);
    }

    /**
     * @param SAMLProviderResponse $response
     * @return array<string,mixed>
     */
    public function present(mixed $response): array
    {
        $loginUrl = $this->router->generate(
            'centreon_application_authentication_login_saml',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return [
            'id' => $response->id,
            'type' => $response->type,
            'name' => $response->name,
            'authentication_uri' => $loginUrl,
            'is_active' => $response->isActive,
            'is_forced' => $response->isForced,
        ];
    }
}
