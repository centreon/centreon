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

namespace App\Security\Infrastructure\Idp;

use App\Security\Domain\Aggregate\Token;
use App\Security\Domain\Aggregate\TokenIdpEnum;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final readonly class IdpFactory
{
    public function __construct(
        #[AutowireLocator([
            TokenIdpEnum::Local->value => LocalIdp::class,
            TokenIdpEnum::OpenId->value => OpenIdIdp::class,
            TokenIdpEnum::Saml->value => SamlIdp::class,
            TokenIdpEnum::WebSso->value => WebSsoIdp::class,
        ])]
        public ContainerInterface $idpLocator,
    ) {
    }

    public function create(Token $token): IdpInterface
    {
        if (!$this->idpLocator->has($token->idp->value)) {
            throw new \OutOfBoundsException(sprintf('Cannot find IDP for "%s".', $token->idp->value));
        }

        return $this->idpLocator->get($token->idp->value);
    }

    public function createByIdpEnum(TokenIdpEnum $idpEnum): IdpInterface
    {
        if (!$this->idpLocator->has($idpEnum->value)) {
            throw new \OutOfBoundsException(sprintf('Cannot find IDP for "%s".', $idpEnum->value));
        }

        return $this->idpLocator->get($idpEnum->value);
    }
}
