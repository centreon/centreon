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

namespace Core\Security\Authentication\Application\UseCase\Autologin;

use Assert\AssertionFailedException;
use Centreon\Domain\Authentication\Exception\AuthenticationException as LegacyAuthenticationException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Exception\ContactDisabledException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Menu\Interfaces\MenuServiceInterface;
use Centreon\Domain\Menu\Model\Page;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorAuthenticationConditionsResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\UnauthorizedResponse;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Authentication\Application\UseCase\Login\ErrorAclConditionsResponse;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Domain\Exception\AclConditionsException;
use Core\Security\Authentication\Domain\Exception\AuthenticationConditionsException;
use Core\Security\Authentication\Domain\Exception\AuthenticationException;
use Core\Security\Authentication\Domain\Exception\AutologinException;
use Core\Security\Authentication\Domain\Exception\PasswordExpiredException;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use Core\Security\Authentication\Infrastructure\Provider\AclUpdaterInterface;
use Core\Security\Authentication\Infrastructure\Provider\Local;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Pimple\Container;
use Security\Domain\Authentication\Model\Session;
use Security\Encryption;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class Autologin
{
    use LoggerTrait;

    /** @var Local */
    private Local $provider;

    /**
     * @param ProviderAuthenticationFactoryInterface $providerFactory
     * @param SessionInterface $session
    //  * @param DataStorageEngineInterface $dataStorageEngine
     * @param WriteSessionRepositoryInterface $sessionRepository
    //  * @param ReadTokenRepositoryInterface $readTokenRepository
    //  * @param WriteTokenRepositoryInterface $writeTokenRepository
    //  * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     * @param AclUpdaterInterface $aclUpdater
    //  * @param MenuServiceInterface $menuService
    //  * @param string $defaultRedirectUri
    //  * @param ThirdPartyAutologinForm $thirdPartyAutologinForm
     */
    public function __construct(
        private Container $dependencyInjector,
        private ProviderAuthenticationFactoryInterface $providerFactory,
        private SessionInterface $session,
        // private DataStorageEngineInterface $dataStorageEngine,
        private WriteSessionRepositoryInterface $sessionRepository,
        // private ReadTokenRepositoryInterface $readTokenRepository,
        // private WriteTokenRepositoryInterface $writeTokenRepository,
        // private WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
        private AclUpdaterInterface $aclUpdater,
        // private MenuServiceInterface $menuService,
        // private string $defaultRedirectUri,
        // private readonly ThirdPartyAutologinForm $thirdPartyAutologinForm,
        private readonly ReadTokenRepositoryInterface $readAppTokenRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly bool $isCloudPlatform,
    ) {
        $this->provider = $this->providerFactory->create(Provider::LOCAL);
    }

    /**
     * @param AutologinRequest $loginRequest
     * @param PresenterInterface $presenter
     *
     * @throws AuthenticationException
     */
    public function __invoke(AutologinRequest $request, PresenterInterface $presenter): void
    {
        try {

            // TODO: check if autologin is allowed on the platform
            if ($this->isCloudPlatform) {
                throw AutologinException::autologinNotAllowed();
            }

            Assertion::notEmptyString($request->token, 'token');
            Assertion::notEmptyString($request->target, 'target');

            // if (! str_starts_with($request->target, 'https')) {
            //     throw AutologinException::unsecureTarget();
            // }

            $user = $this->getUserOrFail($request->token);

            $this->createSession($user);

            $this->updateACL($user);

            $presenter->present(
                new AutologinResponse(
                    (string) $this->session->getId(),
                    (string) $request->target
                )
            );
        } catch (AssertionFailedException $exception) {
            $this->error('Missing query parameters', ['trace' => (string) $exception]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($exception->getMessage()));

            return;
        } catch (AutologinException $exception) {
            $this->error('An error occurred during authentication', ['trace' => (string) $exception]);
            $presenter->setResponseStatus(new UnauthorizedResponse($exception->getMessage()));

            return;
        } catch (AclConditionsException $exception) {
            $this->error('An error occured while matching your ACL conditions', ['trace' => (string) $exception]);
            $presenter->setResponseStatus(new ErrorAclConditionsResponse($exception->getMessage()));
        } catch (\Throwable $ex) {
            $this->error('An error occurred during authentication', ['trace' => (string) $ex]);
            $presenter->setResponseStatus(new ErrorResponse('An error occurred during authentication'));

            return;
        }
    }

    private function getUserOrFail(string $tokenString): ContactInterface
    {
        $user = $this->contactRepository->findByAuthenticationToken($tokenString);
        if ($user === null) {
            $this->error('An error occured during user retrieval');
            throw AutologinException::invalidAppToken();
        }
        if (! $user->isActive()) {
            $this->error('An error occured during user retrieval');
            throw AutologinException::invalidAppToken();
        }
        // TODO: necessity ? redirection will throw an error anyway
        // if (! $user->isAllowedToReachWeb()) {
        //     throw LegacyAuthenticationException::notAllowedToReachWebApplication();
        // }

        return $user;
    }

    /**
     * @throws \Exception
     */
    private function createSession(ContactInterface $user): void
    {
        global $pearDB;
        $pearDB = $this->dependencyInjector['configuration_db'];

        $sessionUserInfos = [
            'contact_id' => $user->getId(),
            'contact_name' => $user->getName(),
            'contact_alias' => $user->getAlias(),
            'contact_email' => $user->getEmail(),
            'contact_lang' => $user->getLang(),
            'contact_passwd' => $user->getEncodedPassword(),
            'contact_autologin_key' => '',
            'contact_admin' => $user->isAdmin() ? '1' : '0',
            'default_page' => $user->getDefaultPage(),
            'contact_location' => (string) $user->getTimezoneId(),
            'show_deprecated_pages' => $user->isUsingDeprecatedPages(),
            'reach_api' => $user->hasAccessToApiConfiguration() ? 1 : 0,
            'reach_api_rt' => $user->hasAccessToApiRealTime() ? 1 : 0,
            'contact_theme' => $user->getTheme() ?? 'light',
            'auth_type' => Provider::LOCAL,
        ];

        $this->provider->setLegacySession(new \Centreon($sessionUserInfos));

        if (! $this->sessionRepository->start($this->provider->getLegacySession())) {
            $this->error('could not start legacy session');
            throw LegacyAuthenticationException::notAuthenticated();
        }
    }


    // /**
    //  * Get the redirection uri where user will be redirect once logged.
    //  *
    //  * @param ContactInterface $authenticatedUser
    //  * @param string|null $refererQueryParameters
    //  *
    //  * @return array<string,bool|string>
    //  */
    // private function getRedirectionInfo(ContactInterface $authenticatedUser, ?string $refererQueryParameters): array
    // {
    //     $refererRedirectionPage = $this->getRedirectionPageFromRefererQueryParameters($refererQueryParameters);
    //     if ($refererRedirectionPage !== null) {
    //         $redirectionInfo = $this->buildDefaultRedirectionUri($refererRedirectionPage);
    //     } elseif ($authenticatedUser->getDefaultPage()?->getUrl() !== null) {
    //         $redirectionInfo = $this->buildDefaultRedirectionUri($authenticatedUser->getDefaultPage());
    //     } else {
    //         $redirectionInfo['redirect_uri'] = $this->defaultRedirectUri;
    //         $redirectionInfo['is_react'] = true;
    //     }

    //     return $redirectionInfo;
    // }

    // /**
    //  * build the redirection uri based on isReact page property.
    //  *
    //  * @param Page $defaultPage
    //  *
    //  * @return array<string,bool|string>
    //  */
    // private function buildDefaultRedirectionUri(Page $defaultPage): array
    // {
    //     $redirectionInfo = [
    //         'is_react' => $defaultPage->isReact(),
    //     ];
    //     if ($defaultPage->isReact() === true) {
    //         $redirectionInfo['redirect_uri'] = $defaultPage->getUrl();
    //     } else {
    //         $redirectUri = '/main.php?p=' . $defaultPage->getPageNumber();
    //         if ($defaultPage->getUrlOptions() !== null) {
    //             $redirectUri .= $defaultPage->getUrlOptions();
    //         }
    //         $redirectionInfo['redirect_uri'] = $redirectUri;
    //     }

    //     return $redirectionInfo;
    // }

    // /**
    //  * Get a Page from referer page number.
    //  *
    //  * @param string|null $refererQueryParameters
    //  *
    //  * @return Page|null
    //  */
    // private function getRedirectionPageFromRefererQueryParameters(?string $refererQueryParameters): ?Page
    // {
    //     if ($refererQueryParameters === null) {
    //         return null;
    //     }

    //     $refererRedirectionPage = null;
    //     $queryParameters = [];
    //     parse_str($refererQueryParameters, $queryParameters);
    //     if (array_key_exists('redirect', $queryParameters) && is_string($queryParameters['redirect'])) {
    //         $redirectionPageParameters = [];
    //         parse_str($queryParameters['redirect'], $redirectionPageParameters);
    //         if (array_key_exists('p', $redirectionPageParameters)) {
    //             $refererRedirectionPage = $this->menuService->findPageByTopologyPageNumber(
    //                 (int) $redirectionPageParameters['p']
    //             );
    //             unset($redirectionPageParameters['p']);
    //             if ($refererRedirectionPage !== null) {
    //                 $refererRedirectionPage->setUrlOptions('&' . http_build_query($redirectionPageParameters));
    //             }
    //         }
    //     }

    //     return $refererRedirectionPage;
    // }

    /**
     * @param ContactInterface $user
     */
    private function updateACL(ContactInterface $user): void
    {
        $this->aclUpdater->updateForProviderAndUser($this->provider, $user);
    }
}
