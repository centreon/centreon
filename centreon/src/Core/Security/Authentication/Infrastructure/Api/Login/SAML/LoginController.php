<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Authentication\Infrastructure\Api\Login\SAML;

use Centreon\Application\Controller\AbstractController;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Infrastructure\Provider\ProviderAuthenticationFactory;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\AuthnRequest;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Security\Domain\Authentication\Exceptions\ProviderException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends AbstractController
{
    use HttpUrlTrait;

    /**
     * @param ProviderAuthenticationFactory $providerAuthenticationFactory
     */
    public function __construct(private readonly ProviderAuthenticationFactory $providerAuthenticationFactory)
    {
    }

    /**
     * @param Request $request
     * @return void
     * @throws Error
     * @throws ProviderException
     */
    public function __invoke(Request $request): void
    {
        /** @var SAML $provider */
        $provider = $this->providerAuthenticationFactory->create(Provider::SAML);
        $provider->login();
   }
}
