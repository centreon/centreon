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

use Centreon;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Menu\MenuService;
use Centreon\Domain\Menu\Model\Page;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Centreon\Infrastructure\Service\Exception\NotFoundException;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\Application\ProviderConfiguration\WebSSO\Repository\ReadWebSSOConfigurationRepositoryInterface;
use Core\Security\Domain\Authentication\SSOAuthenticationException;
use Core\Security\Domain\ProviderConfiguration\WebSSO\Model\WebSSOConfiguration;
use DateInterval;
use DateTime;
use Exception;
use FOS\RestBundle\View\View;
use Pimple\Container;
use Security\Domain\Authentication\Interfaces\AuthenticationRepositoryInterface;
use Security\Domain\Authentication\Interfaces\AuthenticationServiceInterface;
use Security\Domain\Authentication\Interfaces\SessionRepositoryInterface;
use Security\Domain\Authentication\Model\ProviderToken;
use Security\Domain\Authentication\Model\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Core\Security\Domain\Authentication\AuthenticationException as CentreonAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Class used to authenticate a request by using a session id.
 *
 * @package Security
 */
class WebSSOAuthenticator extends AbstractAuthenticator
{
    use HttpUrlTrait;
    use LoggerTrait;

    /**
     * @param Container $dependencyInjector
     * @param ReadWebSSOConfigurationRepositoryInterface $webSSOReadRepository
     * @param ContactRepositoryInterface $contactRepository
     * @param SessionInterface $session
     * @param AuthenticationServiceInterface $authenticationService
     * @param SessionRepositoryInterface $sessionRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param OptionServiceInterface $optionService
     * @param AuthenticationRepositoryInterface $authenticationRepository
     * @param Security $security
     * @param MenuService $menuService
     */
    public function __construct(
        private Container $dependencyInjector,
        private ReadWebSSOConfigurationRepositoryInterface $webSSOReadRepository,
        private ContactRepositoryInterface $contactRepository,
        private SessionInterface $session,
        private AuthenticationServiceInterface $authenticationService,
        private SessionRepositoryInterface $sessionRepository,
        private DataStorageEngineInterface $dataStorageEngine,
        private OptionServiceInterface $optionService,
        private AuthenticationRepositoryInterface $authenticationRepository,
        private Security $security,
        private MenuService $menuService
    ) {

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

        $configuration = $this->findWebSSOConfigurationOrFail();
        $sessionId = $request->getSession()->getId();
        $isValidToken = $this->authenticationService->isValidToken($sessionId);

        return !$isValidToken && $configuration->isActive();
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->info(sprintf("WebSSO authentication failed: %s", $exception->getMessage()));
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
     * @param Request $request
     * @return SelfValidatingPassport
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        try {
            $webSSOConfiguration = $this->findWebSSOConfigurationOrFail();
            $this->info('Starting authentication with WebSSO');
            $this->validateIpIsAllowToConnect($request->getClientIp(), $webSSOConfiguration);
            $this->validateLoginAttributeOrFail($webSSOConfiguration);

            $userAlias = $_SERVER[$webSSOConfiguration->getLoginHeaderAttribute()];
            if ($webSSOConfiguration->getPatternMatchingLogin() !== null) {
                $userAlias = $this->extractUsernameFromLoginClaimOrFail($webSSOConfiguration);
            }
            $user = $this->findUserByAliasOrFail($userAlias);
            $this->createSession($user, $request);
            $sessionId = $request->getSession()->getId();
            $request->headers->set('Set-Cookie', "PHPSESSID=" . $sessionId);
            $this->createTokenIfNotExist(
                $sessionId,
                $webSSOConfiguration->getId(),
                $user,
                $request->getClientIp()
            );
            $this->info('Authenticated successfully', [
                'user' => $user->getAlias()
            ]);

            $referer = $request->headers->get('referer') ?
                parse_url(
                    $request->headers->get('referer'),
                    PHP_URL_QUERY
                ) : null;

            View::createRedirect(
                $this->getRedirectionUri(
                    $user,
                    $referer
                ),
                200
            );
        } catch (SSOAuthenticationException $exception) {
            throw new AuthenticationException($exception->getMessage(), $exception->getCode());
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $sessionId,
                function () use ($user) {
                    return $user;
                }
            )
        );
    }

    /**
     * Extract username using configured regexp for login matching
     *
     * @param WebSSOConfiguration $webSSOConfiguration
     * @return string
     * @throws SSOAuthenticationException
     */
    private function extractUsernameFromLoginClaimOrFail(WebSSOConfiguration $webSSOConfiguration): string
    {
        $this->info('Retrieving username from login claim');
        $userAlias = preg_replace(
            '/' . trim($webSSOConfiguration->getPatternMatchingLogin(), '/') . '/',
            $webSSOConfiguration->getPatternReplaceLogin() ?? '',
            $_SERVER[$webSSOConfiguration->getLoginHeaderAttribute()]
        );
        if (empty($userAlias)) {
            $this->error('Regex does not match anything', [
                'regex' => $webSSOConfiguration->getPatternMatchingLogin(),
                'subject' => $_SERVER[$webSSOConfiguration->getLoginHeaderAttribute()]
            ]);
            throw SSOAuthenticationException::unableToRetrieveUsernameFromLoginClaim();
        }

        return $userAlias;
    }

    /**
     * @param string $ipAddress
     * @param WebSSOConfiguration $webSSOConfiguration
     * @throws SSOAuthenticationException
     */
    private function validateIpIsAllowToConnect(string $ipAddress, WebSSOConfiguration $webSSOConfiguration): void
    {
        $this->info('Check Client IP from blacklist/whitelist addresses');
        if (in_array($ipAddress, $webSSOConfiguration->getBlackListClientAddresses(), true)) {
            $this->error('IP Blacklisted', ['ip' => '...' . substr($ipAddress, -5)]);
            throw SSOAuthenticationException::blackListedClient();
        }
        if (
            !empty($webSSOConfiguration->getTrustedClientAddresses())
            && !in_array($ipAddress, $webSSOConfiguration->getTrustedClientAddresses(), true)
        ) {
            $this->error('IP not Whitelisted', ['ip' => '...' . substr($ipAddress, -5)]);
            throw SSOAuthenticationException::notWhiteListedClient();
        }
    }

    /**
     * Find Web SSO Configuration or throw an exception
     *
     * @return WebSSOConfiguration
     * @throws NotFoundException
     */
    private function findWebSSOConfigurationOrFail(): WebSSOConfiguration
    {
        $this->info('finding web-sso configuration');
        $webSSOConfiguration = $this->webSSOReadRepository->findConfiguration();
        if ($webSSOConfiguration === null) {
            throw new NotFoundException('Web SSO Configuration does not exist');
        }

        return $webSSOConfiguration;
    }

    /**
     * Validate that login attribute is defined in server environment variables
     *
     * @param WebSSOConfiguration $webSSOConfiguration
     * @throws SSOAuthenticationException
     */
    private function validateLoginAttributeOrFail(WebSSOConfiguration $webSSOConfiguration): void
    {
        $this->info('Validating login header attribute');
        if (!array_key_exists($webSSOConfiguration->getLoginHeaderAttribute(), $_SERVER)) {
            $this->error('login header attribute not found in server environment server', [
                'login_header_attribute' => $webSSOConfiguration->getLoginHeaderAttribute()
            ]);

            throw SSOAuthenticationException::missingRemoteLoginAttribute();
        }
    }

    /**
     * Find User or throw an exception
     *
     * @param string $alias
     * @return Contact
     * @throws NotFoundException
     */
    private function findUserByAliasOrFail(string $alias): Contact
    {
        $this->info('searching user', [
            'user' => $alias
        ]);
        $user = $this->contactRepository->findByName($alias);
        if ($user === null) {
            throw new NotFoundException("Contact $alias does not exists");
        }

        return $user;
    }

    /**
     * Create the session
     *
     * @param Contact $user
     * @param Request $request
     */
    private function createSession(Contact $user, Request $request): void
    {
        $this->info('creating session');
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
            'contact_theme' => $user->getTheme() ?? 'light'
        ];
        $centreonSession = new Centreon($sessionUserInfos);
        $request->getSession()->start();
        $request->getSession()->set('centreon', $centreonSession);
        $_SESSION['centreon'] = $centreonSession;
    }

    /**
     * Create token if not exist
     *
     * @param string $sessionId
     * @param integer $webSSOConfigurationId
     * @param Contact $user
     * @param string $clientIp
     */
    private function createTokenIfNotExist(
        string $sessionId,
        int $webSSOConfigurationId,
        Contact $user,
        string $clientIp
    ): void {
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
                new DateTime(),
                (new DateTime())->add(new DateInterval('PT' . $sessionExpirationDelay . 'M'))
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
     * @throws \Core\Security\Domain\Authentication\AuthenticationException
     */
    private function createAuthenticationTokens(
        string $sessionToken,
        ContactInterface $contact,
        ProviderToken $providerToken,
        ?ProviderToken $providerRefreshToken,
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
            $this->authenticationRepository->addAuthenticationTokens(
                $sessionToken,
                $providerToken->getId(),
                $contact->getId(),
                $providerToken,
                $providerRefreshToken
            );
            if (!$isAlreadyInTransaction) {
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (Exception $ex) {
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
