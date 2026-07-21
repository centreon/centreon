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

namespace Core\Security\Authentication\Application\UseCase\LogoutSession\SAML;

use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Domain\Exception\ProviderException;
use Core\Security\Authentication\Domain\Exception\SamlException;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;

readonly class LogoutFromIdp
{
    public function __construct(
        private ProviderAuthenticationFactoryInterface $providerFactory,
    ) {
    }

    /**
     * @throws SamlException
     * @throws ProviderException
     */
    public function __invoke(): void
    {
        // /saml/sls is the IdP callback endpoint: the IdP calls it with a SAMLRequest (IdP-initiated SLO)
        // or a SAMLResponse (response to a Centreon-initiated SLO). We only process that callback here;
        // initiating the LogoutRequest is the responsibility of the LogoutSession use case.
        /** @var SAML $provider */
        $provider = $this->providerFactory->create(Provider::SAML);
        if ($provider->getConfiguration()->isActive()) {
            $provider->handleCallbackLogoutResponse();
        }
    }
}
