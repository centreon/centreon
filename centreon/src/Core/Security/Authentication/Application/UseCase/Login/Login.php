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

namespace Core\Security\Authentication\Application\UseCase\Login;

use Centreon\Domain\Authentication\Exception\AuthenticationException as LegacyAuthenticationException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Menu\Interfaces\MenuServiceInterface;
use Centreon\Domain\Menu\Model\Page;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorAuthenticationConditionsResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\UnauthorizedResponse;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\Authentication\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Domain\Exception\AclConditionsException;
use Core\Security\Authentication\Domain\Exception\AuthenticationConditionsException;
use Core\Security\Authentication\Domain\Exception\AuthenticationException;
use Core\Security\Authentication\Domain\Exception\PasswordExpiredException;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use Core\Security\Authentication\Infrastructure\Provider\AclUpdaterInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Security\Domain\Authentication\Model\Session;
use Security\Encryption;
use Symfony\Component\HttpFoundation\RequestStack;

final class Login
{
    use LoggerTrait;

    /** @var ProviderAuthenticationInterface */
    private ProviderAuthenticationInterface $provider;

    /**
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     * @param RequestStack $requestStack
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param WriteSessionRepositoryInterface $sessionRepository
     * @param ReadTokenRepositoryInterface $readTokenRepository
     * @param WriteTokenRepositoryInterface $writeTokenRepository
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param AclUpdaterInterface $aclUpdater
     * @param MenuServiceInterface $menuService
     * @param string $defaultRedirectUri
     * @param ThirdPartyLoginForm $thirdPartyLoginForm
     */
    public function __construct(
        private ProviderAuthenticationFactoryInterface $providerFactory,
        private RequestStack $requestStack,
        private DataStorageEngineInterface $dataStorageEngine,
        private WriteSessionRepositoryInterface $sessionRepository,
        private ReadTokenRepositoryInterface $readTokenRepository,
        private WriteTokenRepositoryInterface $writeTokenRepository,
        private WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private AclUpdaterInterface $aclUpdater,
        private MenuServiceInterface $menuService,
        private string $defaultRedirectUri,
        private readonly ThirdPartyLoginForm $thirdPartyLoginForm,
    ) {
    }

    /**
     * @param LoginRequest $loginRequest
     * @param PresenterInterface $presenter
     */
    public function __invoke(LoginRequest $loginRequest, PresenterInterface $presenter): void
    {
        try {
            $this->provider = $this->providerFactory->create($loginRequest->providerName);

            $this->provider->authenticateOrFail($loginRequest);

            if ($this->provider->isAutoImportEnabled()) {
                $this->provider->importUser();
            }

            $user = $this->provider->findUserOrFail();
            if ($loginRequest->providerName === Provider::LOCAL && ! $user->isAllowedToReachWeb()) {
                throw LegacyAuthenticationException::notAllowedToReachWebApplication();
            }

            $this->updateACL($user);

            $token = null;
            if ($this->sessionRepository->start($this->provider->getLegacySession())) {
                if ($this->readTokenRepository->hasAuthenticationTokensByToken($this->requestStack->getSession()->getId()) === false) {
                    if ($loginRequest->providerName === Provider::SAML && $this->thirdPartyLoginForm->isActive()) {
                        // We create an API token in addition of the session token.
                        $this->createAuthenticationTokens(
                            $token = Encryption::generateRandomString(),
                            $user,
                            $this->provider->getProviderToken($this->requestStack->getSession()->getId()),
                            $this->provider->getProviderRefreshToken(),
                            $loginRequest->clientIp
                        );
                        // We pass the token to let the third party login form propagate to the form.
                        $this->thirdPartyLoginForm->setToken($token);
                    }

                    // Session token To keep the stateful authentication active anyway.
                    $this->createAuthenticationTokens(
                        $this->requestStack->getSession()->getId(),
                        $user,
                        $this->provider->getProviderToken($this->requestStack->getSession()->getId()),
                        $this->provider->getProviderRefreshToken(),
                        $loginRequest->clientIp
                    );
                }

            }

            $redirectionInfo = $this->getRedirectionInfo($user, $loginRequest->refererQueryParameters);
            $presenter->present(
                new LoginResponse(
                    (string) $redirectionInfo['redirect_uri'],
                    (bool) $redirectionInfo['is_react'],
                )
            );
        } catch (PasswordExpiredException $exception) {
            $this->info('The password expired', ['trace' => (string) $exception]);
            $response = new PasswordExpiredResponse($exception->getMessage());
            $response->setBody(['password_is_expired' => true]);
            $presenter->setResponseStatus($response);

            return;
        } catch (AuthenticationException $exception) {
            $this->error('An error occurred during authentication', ['trace' => (string) $exception]);
            $presenter->setResponseStatus(new UnauthorizedResponse($exception->getMessage()));

            return;
        } catch (AclConditionsException $exception) {
            $this->error('An error occured while matching your ACL conditions', ['trace' => (string) $exception]);
            $presenter->setResponseStatus(new ErrorAclConditionsResponse($exception->getMessage()));
        } catch (AuthenticationConditionsException $ex) {
            $this->error('An error occured while matching your authentication conditions', ['trace' => (string) $ex]);
            $presenter->setResponseStatus(new ErrorAuthenticationConditionsResponse($ex->getMessage()));

            return;
        } catch (\Throwable $ex) {
            $this->error('An error occurred during authentication', ['trace' => (string) $ex]);
            $presenter->setResponseStatus(new ErrorResponse('An error occurred during authentication'));

            return;
        }
    }

    /**
     * Create Authentication tokens.
     *
     * @param string $sessionToken
     * @param ContactInterface $contact
     * @param NewProviderToken $providerToken
     * @param NewProviderToken|null $providerRefreshToken
     * @param string|null $clientIp
     *
     * @throws AuthenticationException
     */
    private function createAuthenticationTokens(
        string $sessionToken,
        ContactInterface $contact,
        NewProviderToken $providerToken,
        ?NewProviderToken $providerRefreshToken,
        ?string $clientIp,
    ): void {

        $isAlreadyInTransaction = $this->dataStorageEngine->isAlreadyinTransaction();

        if (! $isAlreadyInTransaction) {
            $this->dataStorageEngine->startTransaction();
        }

        try {
            $session = new Session($sessionToken, $contact->getId(), $clientIp);
            $this->writeSessionTokenRepository->createSession($session);
            $this->writeTokenRepository->createAuthenticationTokens(
                $sessionToken,
                $this->provider->getConfiguration()->getId(),
                $contact->getId(),
                $providerToken,
                $providerRefreshToken
            );
            if (! $isAlreadyInTransaction) {
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (\Exception) {
            if (! $isAlreadyInTransaction) {
                $this->dataStorageEngine->rollbackTransaction();
            }

            throw AuthenticationException::notAuthenticated();
        }
    }

    /**
     * Get the redirection uri where user will be redirect once logged.
     *
     * @param ContactInterface $authenticatedUser
     * @param string|null $refererQueryParameters
     *
     * @return array<string,bool|string>
     */
    private function getRedirectionInfo(ContactInterface $authenticatedUser, ?string $refererQueryParameters): array
    {
        $refererRedirectionPage = $this->getRedirectionPageFromRefererQueryParameters($refererQueryParameters);
        if ($refererRedirectionPage !== null) {
            $redirectionInfo = $this->buildDefaultRedirectionUri($refererRedirectionPage);
        } elseif ($authenticatedUser->getDefaultPage()?->getUrl() !== null) {
            $redirectionInfo = $this->buildDefaultRedirectionUri($authenticatedUser->getDefaultPage());
        } else {
            $redirectionInfo['redirect_uri'] = $this->defaultRedirectUri;
            $redirectionInfo['is_react'] = true;
        }

        return $redirectionInfo;
    }

    /**
     * build the redirection uri based on isReact page property.
     *
     * @param Page $defaultPage
     *
     * @return array<string,bool|string>
     */
    private function buildDefaultRedirectionUri(Page $defaultPage): array
    {
        $redirectionInfo = [
            'is_react' => $defaultPage->isReact(),
        ];
        if ($defaultPage->isReact() === true) {
            $redirectionInfo['redirect_uri'] = $defaultPage->getUrl();
        } else {
            $redirectUri = '/main.php?p=' . $defaultPage->getPageNumber();
            if ($defaultPage->getUrlOptions() !== null) {
                $redirectUri .= $defaultPage->getUrlOptions();
            }
            $redirectionInfo['redirect_uri'] = $redirectUri;
        }

        return $redirectionInfo;
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
        if (array_key_exists('redirect', $queryParameters) && is_string($queryParameters['redirect'])) {
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

    /**
     * @param ContactInterface $user
     */
    private function updateACL(ContactInterface $user): void
    {
        $this->aclUpdater->updateForProviderAndUser($this->provider, $user);
    }
}
