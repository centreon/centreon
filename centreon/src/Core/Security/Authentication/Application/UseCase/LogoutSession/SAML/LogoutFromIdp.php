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

namespace Core\Security\Authentication\Application\UseCase\LogoutSession\SAML;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;

class LogoutFromIdp
{
    use LoggerTrait;

    /**
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     */
    public function __construct(
        private readonly WriteSessionRepositoryInterface $writeSessionRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory
    ) {
    }

    public function __invoke(): void
    {
        session_start();
        $this->info("SAML SLS invoked");
        /** @var SAML $provider */
        $provider = $this->providerFactory->create(Provider::SAML);
        $this->writeSessionRepository->invalidate();
        $provider->handleCallbackLogoutResponse();
    }
}
