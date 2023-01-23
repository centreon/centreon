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

namespace Security;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Exception\ContactDisabledException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Menu\Interfaces\MenuServiceInterface;
use Centreon\Domain\Menu\Model\Page;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use Core\Security\Authentication\Domain\Model\ProviderToken;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use DateInterval;
use DateTimeImmutable;
use FOS\RestBundle\View\View;
use Security\Domain\Authentication\Interfaces\AuthenticationServiceInterface;
use Security\Domain\Authentication\Interfaces\SessionRepositoryInterface;
use Security\Domain\Authentication\Model\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Core\Security\Authentication\Domain\Exception\AuthenticationException as CentreonAuthenticationException;

class WebSSOAuthenticator extends AbstractAuthenticator
{
    use HttpUrlTrait;
    use LoggerTrait;

    /**
     * @var ProviderAuthenticationInterface
     */
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
        private MenuServiceInterface $menuService
    ) {
        $this->provider = $this->providerFactory->create(Provider::WEB_SSO);
    }

    /**
     * @inheritDoc
     */
    public function supports(Request $request): bool
    {
        // We skip all API calls
        if ($request->headers->has('X-Auth-Token')) {
            return false;
        }

        $provider = $this->providerFactory->create(Provider::WEB_SSO);
        $configuration = $provider->getConfiguration();

        $sessionId = $request->getSession()->getId();
        $isValidToken = $this->authenticationService->isValidToken($sessionId);

        return !$isValidToken && $configuration->isActive();
    }

    /**
     * @inheritDoc
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
     * {@inheritdoc}
     *
     * @param Request $request
     * @return SelfValidatingPassport
     * @throws SSOAuthenticationException
     * @throws \Core\Security\Authentication\Domain\Exception\AuthenticationException
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        try {
            $this->createSession($request, $this->provider);
            $this->info('Starting authentication with WebSSO with session id ' . $request->getSession()->getId());
            $this->provider->authenticateOrFail(
                LoginRequest::createForSSO($request->getClientIp())
            );
            $user = $this->provider->findUserOrFail();
            $this->info('Authenticated successfully', ['user' => $user->getAlias()]);

            $referer = $request->headers->get('referer') ?
                parse_url(
                    $request->headers->get('referer'),
                    PHP_URL_QUERY
                ) : null;


            View::createRedirect(
                $this->getRedirectionUri(
                    $this->provider->getAuthenticatedUser(),
                    $referer
                ),
                200
            );
        } catch (SSOAuthenticationException $exception) {
            throw new AuthenticationException($exception->getMessage(), $exception->getCode());
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
     * @return UserInterface
     * @throws BadCredentialsException
     * @throws SessionUnavailableException
     * @throws ContactDisabledException
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
        if (!$contact->isActive()) {
            throw new ContactDisabledException();
        }

        return $contact;
    }

    /**
     * Create the session
     *
     * @param Request $request
     * @param ProviderAuthenticationInterface $provider
     * @throws \Centreon\Domain\Authentication\Exception\AuthenticationException
     */
    private function createSession(Request $request, ProviderAuthenticationInterface $provider): void
    {
        $this->debug('Creating session');

        if ($this->writeSessionRepository->start($provider->getLegacySession())) {
            $sessionId = $request->getSession()->getId();
            $this->createTokenIfNotExist(
                $sessionId,
                $provider->getConfiguration()->getId(),
                $provider->getAuthenticatedUser(),
                $request->getClientIp()
            );
            $request->headers->set('Set-Cookie', "PHPSESSID=" . $sessionId);
        }
    }

    /**
     * Create token if not exist
     *
     * @param string $sessionId
     * @param integer $webSSOConfigurationId
     * @param ContactInterface $user
     * @param string $clientIp
     * @throws \Centreon\Domain\Authentication\Exception\AuthenticationException
     */
    private function createTokenIfNotExist(
        string $sessionId,
        int $webSSOConfigurationId,
        ContactInterface $user,
        string $clientIp
    ): void
    {
        $this->info('creating token');
        $authenticationTokens = $this->authenticationService->findAuthenticationTokensByToken(
            $sessionId
        );
        if ($authenticationTokens === null) {
            $sessionExpireOption = $this->optionService->findSelectedOptions(['session_expire']);
            $sessionExpirationDelay = (int)$sessionExpireOption[0]->getValue();
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
    }

    /**
     * create Authentication tokens
     *
     * @param string $sessionToken
     * @param ContactInterface $contact
     * @param ProviderToken $providerToken
     * @param ProviderToken|null $providerRefreshToken
     * @param string|null $clientIp
     * @throws CentreonAuthenticationException
     */
    private function createAuthenticationTokens(
        string $sessionToken,
        ContactInterface $contact,
        NewProviderToken $providerToken,
        ?NewProviderToken $providerRefreshToken,
        ?string $clientIp,
    ): void
    {
        $isAlreadyInTransaction = $this->dataStorageEngine->isAlreadyinTransaction();

        if (!$isAlreadyInTransaction) {
            $this->dataStorageEngine->startTransaction();
        }
        try {
            $session = new Session($sessionToken, $contact->getId(), $clientIp);
            $this->sessionRepository->addSession($session);
            $this->writeTokenRepository->createAuthenticationTokens(
                $sessionToken,
                $providerToken->getId(),
                $contact->getId(),
                $providerToken,
                $providerRefreshToken
            );
            if (!$isAlreadyInTransaction) {
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (\Exception $ex) {
            $this->error('Unable to create authentication tokens', [
                'trace' => $ex->getTraceAsString()
            ]);
            if (!$isAlreadyInTransaction) {
                $this->dataStorageEngine->rollbackTransaction();
            }
            throw CentreonAuthenticationException::notAuthenticated();
        }
    }

    /**
     * Get the redirection uri where user will be redirect once logged.
     *
     * @param ContactInterface $authenticatedUser
     * @param string|null $refererQueryParameters
     * @return string
     */
    private function getRedirectionUri(ContactInterface $authenticatedUser, ?string $refererQueryParameters): string
    {
        $redirectionUri = '/monitoring/resources';

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
     * @return string
     */
    private function buildDefaultRedirectionUri(Page $defaultPage): string
    {
        if ($defaultPage->isReact() === true) {
            return $defaultPage->getUrl();
        }
        $redirectUri = "/main.php?p=" . $defaultPage->getPageNumber();
        if ($defaultPage->getUrlOptions() !== null) {
            $redirectUri .= $defaultPage->getUrlOptions();
        }

        return $redirectUri;
    }

    /**
     * Get a Page from referer page number.
     *
     * @param string|null $refererQueryParameters
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
        if (array_key_exists('redirect', $queryParameters)) {
            $redirectionPageParameters = [];
            parse_str($queryParameters['redirect'], $redirectionPageParameters);
            if (array_key_exists('p', $redirectionPageParameters)) {
                $refererRedirectionPage = $this->menuService->findPageByTopologyPageNumber(
                    (int)$redirectionPageParameters['p']
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