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

namespace Core\Security\Authentication\Infrastructure\Api\Login\SAML;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorAuthenticationConditionsResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\UnauthorizedResponse;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Application\UseCase\Login\ErrorAclConditionsResponse;
use Core\Security\Authentication\Application\UseCase\Login\Login;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Application\UseCase\Login\LoginResponse;
use Core\Security\Authentication\Application\UseCase\Login\PasswordExpiredResponse;
use Core\Security\Authentication\Domain\Exception\AuthenticationException;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter\SettingsFormatterInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use FOS\RestBundle\View\View;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SLSController extends AbstractController
{
    use HttpUrlTrait;
    use LoggerTrait;

    /**
     * @param SettingsFormatterInterface $settingsFormatter
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     * @param RequestStack $requestStack
     */
    public function __construct(
        private readonly SettingsFormatterInterface $settingsFormatter,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory,
        private readonly WriteSessionRepositoryInterface $writeSessionRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param Request $request
     * @return void
     * @throws Error
     */
    public function __invoke(Request $request): void
    {
        session_start();
        $this->info("SAML SLS invoked");
        /** @var SAML $provider */
        $provider = $this->providerFactory->create(Provider::SAML);
        $configuration = $provider->getConfiguration();
        $auth = new Auth($this->settingsFormatter->format($configuration->getCustomConfiguration()));
        if (isset($_SESSION) && isset($_SESSION['LogoutRequestID'])) {
            $requestID = $_SESSION['LogoutRequestID'];
        } else {
            $requestID = null;
        }

        $this->writeSessionRepository->invalidate();

        $auth->processSLO(true, $requestID);

        // Avoid 'Open Redirect' attacks
        if (isset($_GET['RelayState']) && Utils::getSelfURL() != $_GET['RelayState']) {
            $auth->redirectTo($_GET['RelayState']);
            exit;
        }
    }
}
