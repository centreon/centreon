<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Security;

use Centreon\Domain\Contact\Interfaces\{ContactInterface, ContactRepositoryInterface};
use Centreon\Domain\Exception\ContactDisabledException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Menu\Interfaces\MenuServiceInterface;
use Centreon\Domain\Menu\Model\Page;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Platform\Interfaces\PlatformRepositoryInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Centreon\Domain\VersionHelper;
use Core\Common\Domain\Exception\ExceptionFormatter;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\Provider\{
    ProviderAuthenticationFactoryInterface,
    ProviderAuthenticationInterface
};
use Core\Security\Authentication\Application\Repository\{
    WriteSessionRepositoryInterface,
    WriteTokenRepositoryInterface
};
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Domain\Exception\AuthenticationException as CentreonAuthenticationException;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\Authentication\Domain\Model\{NewProviderToken, ProviderToken};
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use DateInterval;
use DateTimeImmutable;
use FOS\RestBundle\View\View;
use Security\Domain\Authentication\Interfaces\{AuthenticationServiceInterface, SessionRepositoryInterface};
use Security\Domain\Authentication\Model\Session;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\{
    AuthenticationException,
    BadCredentialsException,
    CredentialsExpiredException,
    SessionUnavailableException,
    UserNotFoundException
};
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class WebSSOAuthenticator extends AbstractAuthenticator
{
    use HttpUrlTrait;
    use LoggerTrait;
    private const MINIMUM_SUPPORTED_VERSION = '22.04';

    /** @var ProviderAuthenticationInterface */
    private ProviderAuthenticationInterface $provider;

    /**
     * @param AuthenticationServiceInterface $authenticationService
     * @param SessionRepositoryInterface $sessionRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param OptionServiceInterface $optionService
     * @param WriteTokenRepositoryInterface $writeTokenRepository
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     * @param ContactRepositoryInterface $contactRepository
     * @param MenuServiceInterface $menuService
     * @param PlatformRepositoryInterface $platformRepository
     *
     * @throws \Exception
     */
    public function __construct(
        private AuthenticationServiceInterface $authenticationService,
        private SessionRepositoryInterface $sessionRepository,
        private DataStorageEngineInterface $dataStorageEngine,
        private OptionServiceInterface $optionService,
        private WriteTokenRepositoryInterface $writeTokenRepository,
        private WriteSessionRepositoryInterface $writeSessionRepository,
        private ProviderAuthenticationFactoryInterface $providerFactory,
        private ContactRepositoryInterface $contactRepository,
        private MenuServiceInterface $menuService,
        private PlatformRepositoryInterface $platformRepository
    ) {
        /** @var string $webVersion */
        $webVersion = $this->platformRepository->getWebVersion();
        if (VersionHelper::compare($webVersion, self::MINIMUM_SUPPORTED_VERSION, VersionHelper::GE)) {
            $this->provider = $this->providerFactory->create(Provider::WEB_SSO);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    public function supports(Request $request): bool
    {
        /** @var string $webVersion */
        $webVersion = $this->platformRepository->getWebVersion();
        // We skip all API calls
        if (
            $request->headers->has('X-Auth-Token')
            || VersionHelper::compare($webVersion, self::MINIMUM_SUPPORTED_VERSION, VersionHelper::LT)
        ) {
            return false;
        }

        $configuration = $this->provider->getConfiguration();

        $sessionId = $request->getSession()->getId();
        $isValidToken = $this->authenticationService->isValidToken($sessionId);

        return ! $isValidToken && $configuration->isActive();
    }

    /**
     * @inheritDoc
     *
     * @throws SSOAuthenticationException
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->info(sprintf("WebSSO authentication failed: %s\n", $exception->getMessage()));

        throw SSOAuthenticationException::withMessageAndCode($exception->getMessage(), $exception->getCode());
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): ?Response
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param Request $request
     *
     * @throws AuthenticationException
     *
     * @return SelfValidatingPassport
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        try {
            $this->info('Starting authentication with WebSSO');
            $this->provider->authenticateOrFail(
                LoginRequest::createForSSO($request->getClientIp() ?? '')
            );

            $user = $this->provider->findUserOrFail();
            $this->createSession($request, $this->provider);
            $this->info(
                'Authenticated successfully',
                ['user' => $user->getAlias(), 'sessionId' => $request->getSession()->getId()]
            );

            // getRedirectionUri() expects ONLY a string as its second parameter
            $referer = $request->headers->get('referer', '');
            if (! empty($referer)) {
                $referer = parse_url($referer, PHP_URL_QUERY);
                if (! is_string($referer)) {
                    $referer = '';
                }
            }

            View::createRedirect(
                $this->getRedirectionUri(
                    $this->provider->getAuthenticatedUser(),
                    $referer
                ),
                200
            );
        } catch (SSOAuthenticationException|CentreonAuthenticationException $exception) {
            ExceptionLogger::create()->log(
                throwable: $exception,
                context: ['user_id' => $this->provider->getAuthenticatedUser()->getId() ?? 'unknown']
            );
            throw new AuthenticationException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $request->getSession()->getId(),
                function ($userIdentifier) {
                    return $this->getUser($userIdentifier);
                }
            )
        );
    }

    /**
     * Return a UserInterface object based on session id provided.
     *
     * @param string $sessionId
     *
     * @throws BadCredentialsException
     * @throws SessionUnavailableException
     * @throws ContactDisabledException
     *
     * @return UserInterface
     */
    private function getUser(string $sessionId): UserInterface
    {
        $providerToken = $this->provider->getProviderToken($sessionId);

        $expirationDate = $providerToken->getExpirationDate();
        if ($expirationDate !== null && $expirationDate->getTimestamp() < time()) {
            throw new CredentialsExpiredException();
        }

        $contact = $this->contactRepository->findByAuthenticationToken($providerToken->getToken());
        if ($contact === null) {
            throw new UserNotFoundException();
        }
        if (! $contact->isActive()) {
            throw new ContactDisabledException();
        }

        return $contact;
    }

    /**
     * Create the session.
     *
     * @param Request $request
     * @param ProviderAuthenticationInterface $provider
     *
     * @throws CentreonAuthenticationException
     * @return void
     */
    private function createSession(Request $request, ProviderAuthenticationInterface $provider): void
    {
        $this->debug('Creating session');

        try {
            $successStartSession = $this->writeSessionRepository->start($provider->getLegacySession());
        } catch (RepositoryException $exception) {
            throw CentreonAuthenticationException::notAuthenticated($exception);
        }

        if ($successStartSession === true) {
            $sessionId = $request->getSession()->getId();

            // @todo: why are we not using findUserOrFail()?
            $authenticatedUser = $provider->getAuthenticatedUser();
            if (null === $authenticatedUser) {
                throw CentreonAuthenticationException::notAuthenticated();
            }

            $this->createTokenIfNotExist(
                $sessionId,
                $provider->getConfiguration()->getId(),
                $authenticatedUser,
                $request->getClientIp() ?? '' // @todo: what should happen if no IP was found?
            );
            $request->headers->set('Set-Cookie', 'PHPSESSID=' . $sessionId);
        }
    }

    /**
     * Create token if not exist.
     *
     * @param string $sessionId
     * @param int $webSSOConfigurationId
     * @param ContactInterface $user
     * @param string $clientIp
     *
     * @throws CentreonAuthenticationException
     */
    private function createTokenIfNotExist(
        string $sessionId,
        int $webSSOConfigurationId,
        ContactInterface $user,
        string $clientIp
    ): void {
        try {
            $this->info('creating token');
            $authenticationTokens = $this->authenticationService->findAuthenticationTokensByToken(
                $sessionId
            );
            if ($authenticationTokens === null) {
                $sessionExpireOption = $this->optionService->findSelectedOptions(['session_expire']);
                $sessionExpirationDelay = (int) $sessionExpireOption[0]->getValue();
                $token = new ProviderToken(
                    $webSSOConfigurationId,
                    $sessionId,
                    new DateTimeImmutable(),
                    (new DateTimeImmutable())->add(new DateInterval('PT' . $sessionExpirationDelay . 'M'))
                );
                $this->createAuthenticationTokens(
                    $sessionId,
                    $user,
                    $token,
                    null,
                    $clientIp,
                );
            }
        } catch (\Exception $exception) {
            throw CentreonAuthenticationException::notAuthenticated(previous: $exception);
        }

    }

    /**
     * create Authentication tokens.
     *
     * @param string $sessionToken
     * @param ContactInterface $contact
     * @param ProviderToken $providerToken
     * @param ProviderToken|null $providerRefreshToken
     * @param string|null $clientIp
     *
     * @throws CentreonAuthenticationException
     */
    private function createAuthenticationTokens(
        string $sessionToken,
        ContactInterface $contact,
        NewProviderToken $providerToken,
        ?NewProviderToken $providerRefreshToken,
        ?string $clientIp
    ): void {
        $isAlreadyInTransaction = $this->dataStorageEngine->isAlreadyinTransaction();

        try {
            if (! $isAlreadyInTransaction) {
                $this->dataStorageEngine->startTransaction();
            }

            $session = new Session($sessionToken, $contact->getId(), $clientIp);
            $this->sessionRepository->addSession($session);
            $this->writeTokenRepository->createAuthenticationTokens(
                $sessionToken,
                $providerToken->getId(),
                $contact->getId(),
                $providerToken,
                $providerRefreshToken
            );
            if (! $isAlreadyInTransaction) {
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (\Exception $exception) {
            if (! $isAlreadyInTransaction) {
                try {
                    $this->dataStorageEngine->rollbackTransaction();
                } catch (\Exception $rollbackException) {
                    throw CentreonAuthenticationException::notAuthenticated(previous: $rollbackException);
                }
            }
            throw CentreonAuthenticationException::notAuthenticated(previous: $exception);
        }
    }

    /**
     * Get the redirection uri where user will be redirect once logged.
     *
     * @param null|ContactInterface $authenticatedUser
     * @param string|null $refererQueryParameters
     *
     * @return string
     */
    private function getRedirectionUri(
        ?ContactInterface $authenticatedUser,
        ?string $refererQueryParameters
    ): string {
        $redirectionUri = '/monitoring/resources';
        if (null === $authenticatedUser) {
            // The previous version assummed that if no conditions were met, just send this var as-is
            return $redirectionUri;
        }

        $refererRedirectionPage = $this->getRedirectionPageFromRefererQueryParameters($refererQueryParameters);
        if ($refererRedirectionPage !== null) {
            $redirectionUri = $this->buildDefaultRedirectionUri($refererRedirectionPage);
        } elseif ($authenticatedUser->getDefaultPage()?->getUrl() !== null) {
            $redirectionUri = $this->buildDefaultRedirectionUri($authenticatedUser->getDefaultPage());
        }

        return $redirectionUri;
    }

    /**
     * build the redirection uri based on isReact page property.
     *
     * @param Page $defaultPage
     *
     * @return string
     */
    private function buildDefaultRedirectionUri(Page $defaultPage): string
    {
        if ($defaultPage->isReact() === true) {
            return $defaultPage->getUrl();
        }
        $redirectUri = '/main.php?p=' . $defaultPage->getPageNumber();
        if ($defaultPage->getUrlOptions() !== null) {
            $redirectUri .= $defaultPage->getUrlOptions();
        }

        return $redirectUri;
    }

    /**
     * Get a Page from referer page number.
     *
     * @param string|null $refererQueryParameters
     *
     * @return Page|null
     */
    private function getRedirectionPageFromRefererQueryParameters(?string $refererQueryParameters): ?Page
    {
        if ($refererQueryParameters === null) {
            return null;
        }

        $refererRedirectionPage = null;
        $queryParameters = [];
        parse_str($refererQueryParameters, $queryParameters);

        if (is_string($queryParameters['redirect'] ?? null)) {
            $redirectionPageParameters = [];
            parse_str($queryParameters['redirect'], $redirectionPageParameters);
            if (array_key_exists('p', $redirectionPageParameters)) {
                $refererRedirectionPage = $this->menuService->findPageByTopologyPageNumber(
                    (int) $redirectionPageParameters['p']
                );
                unset($redirectionPageParameters['p']);
                if ($refererRedirectionPage !== null) {
                    $refererRedirectionPage->setUrlOptions('&' . http_build_query($redirectionPageParameters));
                }
            }
        }

        return $refererRedirectionPage;
    }
}
