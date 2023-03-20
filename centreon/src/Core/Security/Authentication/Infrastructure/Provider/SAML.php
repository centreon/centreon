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

namespace Core\Security\Authentication\Infrastructure\Provider;

use Assert\AssertionFailedException;
use Centreon;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Entity\ContactGroup;
use Centreon\Domain\Log\LoggerTrait;
use CentreonSession;
use Core\Application\Configuration\User\Repository\WriteUserRepositoryInterface;
use Core\Domain\Configuration\User\Model\NewUser;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\Authentication\Application\UseCase\Login\LoginRequest;
use Core\Security\Authentication\Domain\Exception\AclConditionsException;
use Core\Security\Authentication\Domain\Exception\AuthenticationConditionsException;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\Authentication\Domain\Model\AuthenticationTokens;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use Core\Security\Authentication\Infrastructure\Provider\Exception\InvalidArgumentProvidedException;
use Core\Security\Authentication\Infrastructure\Provider\Exception\InvalidUserIdAttributeException;
use Core\Security\Authentication\Infrastructure\Provider\Exception\SAML\InvalidMetadataException;
use Core\Security\Authentication\Infrastructure\Provider\Exception\SAML\ProcessAuthenticationResponseException;
use Core\Security\Authentication\Infrastructure\Provider\Exception\UserNotAuthenticatedException;
use Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter\SettingsFormatterInterface;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\Conditions;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\GroupsMapping as GroupsMappingSecurityAccess;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\RolesMapping;
use DateInterval;
use DateTimeImmutable;
use Exception;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\ValidationError;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class SAML implements ProviderAuthenticationInterface
{
    use LoggerTrait;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var Centreon
     */
    private Centreon $legacySession;

    /**
     * @var string
     */
    private string $username;

    /**
     * @var ContactInterface|null
     */
    private ?ContactInterface $authenticatedUser = null;

    /**
     * @var Auth|null
     */
    private ?Auth $auth = null;

    /**
     * @param Container $dependencyInjector
     * @param ContactRepositoryInterface $contactRepository
     * @param LoginLoggerInterface $loginLogger
     * @param WriteUserRepositoryInterface $userRepository
     * @param Conditions $conditions
     * @param RolesMapping $rolesMapping
     * @param GroupsMappingSecurityAccess $groupsMapping
     * @param SettingsFormatterInterface $formatter
     * @param SessionInterface $session
     */
    public function __construct(
        private readonly Container $dependencyInjector,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly LoginLoggerInterface $loginLogger,
        private readonly WriteUserRepositoryInterface $userRepository,
        private readonly Conditions $conditions,
        private readonly RolesMapping $rolesMapping,
        private readonly GroupsMappingSecurityAccess $groupsMapping,
        private readonly SettingsFormatterInterface $formatter,
        private readonly SessionInterface $session,
    ) {
    }

    /**
     * @param LoginRequest $request
     * @throws AclConditionsException
     * @throws Error
     * @throws ValidationError
     * @throws AuthenticationConditionsException
     * @throws Exception
     */
    public function authenticateOrFail(LoginRequest $request): void
    {
        $this->loginLogger->info(Provider::SAML, 'authenticate the user through SAML');
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $this->configuration->getCustomConfiguration();
        $this->auth = new Auth($this->formatter->format($customConfiguration));
        $this->auth->processResponse($_SESSION['AuthNRequestID'] ?? null);
        $errors = $this->auth->getErrors();
        if (!empty($errors)) {
            $ex =  ProcessAuthenticationResponseException::create();
            $this->loginLogger->error(Provider::SAML, $ex->getMessage(), ['context' => $errors]);
            throw $ex;
        }

        if (!$this->auth->isAuthenticated()) {
            $ex = UserNotAuthenticatedException::create();
            $this->loginLogger->error(Provider::SAML, $ex->getMessage());
            throw $ex;
        }

        $settings = $this->auth->getSettings();
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);
        if (!empty($errors)) {
            $ex = InvalidMetadataException::create();
            $this->info($ex->getMessage(), ['errors' => $errors]);
            throw $ex;
        }

        $attrs = $this->auth->getAttribute($customConfiguration->getUserIdAttribute());
        if ($attrs === null) {
            throw InvalidUserIdAttributeException::create();
        }

        $this->username = $attrs[0];
        CentreonSession::writeSessionClose('saml', [
            'samlSessionIndex' => $this->auth->getSessionIndex(),
            'samlNameId' => $this->auth->getNameId()
        ]);

        $this->loginLogger->info(Provider::SAML, 'checking security access rules');
        $this->conditions->validate($this->configuration, $this->auth->getAttributes());
        $this->rolesMapping->validate($this->configuration, $this->auth->getAttributes());
        $this->groupsMapping->validate($this->configuration, $this->auth->getAttributes());
    }

    /**
     * @return ContactInterface
     * @throws SSOAuthenticationException
     */
    public function findUserOrFail(): ContactInterface
    {
        $user = $this->contactRepository->findByEmail($this->username);
        if ($user === null) {
            throw SSOAuthenticationException::aliasNotFound($this->username);
        }

        return $user;
    }

    /**
     * @return ContactInterface|null
     * @throws Exception
     */
    public function getUser(): ?ContactInterface
    {
        $this->info('Searching user : ' . $this->username);
        $user = $this->contactRepository->findByName($this->username);
        if ($user === null) {
            $user = $this->contactRepository->findByEmail($this->username);
        }

        return $user;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return bool
     */
    public function isAutoImportEnabled(): bool
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $this->configuration->getCustomConfiguration();
        return $customConfiguration->isAutoImportEnabled();
    }

    /**
     * @throws SSOAuthenticationException
     * @throws Throwable
     */
    public function importUser(): void
    {
        $user = $this->getUser();
        if ($this->isAutoImportEnabled() && $user === null) {
            $this->info("Start auto import");
            $this->loginLogger->info($this->configuration->getType(), 'start auto import');
            $this->createUser();
            $user = $this->findUserOrFail();
            $this->info("User imported: " . $user->getName());
            $this->loginLogger->info(
                $this->configuration->getType(),
                'user imported',
                ['email' => $user->getEmail()]
            );
        }
    }

    /**
     * @throws SSOAuthenticationException
     * @throws Throwable
     */
    public function updateUser(): void
    {
        $user = $this->getAuthenticatedUser();
        if ($this->isAutoImportEnabled() === true && $user === null) {
            $this->info("Start auto import");
            $this->createUser();
            $user = $this->getAuthenticatedUser();
            $this->info("User imported: " . $user->getName());
        }
    }

    /**
     * @return Centreon
     * @throws Exception
     */
    public function getLegacySession(): Centreon
    {
        global $pearDB;
        $pearDB = $this->dependencyInjector['configuration_db'];

        $user = $this->findUserOrFail();
        if ($user === null) {
            throw new Exception("can't initialize legacy session, user does not exist");
        }

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
            'contact_location' => (string)$user->getTimezoneId(),
            'show_deprecated_pages' => $user->isUsingDeprecatedPages(),
            'reach_api' => $user->hasAccessToApiConfiguration() ? 1 : 0,
            'reach_api_rt' => $user->hasAccessToApiRealTime() ? 1 : 0,
            'contact_theme' => $user->getTheme() ?? 'light',
            'auth_type' => Provider::SAML
        ];

        $this->authenticatedUser = $user;
        $this->legacySession = new Centreon($sessionUserInfos);

        return $this->legacySession;
    }

    /**
     * @param string|null $token
     * @return NewProviderToken
     */
    public function getProviderToken(?string $token = null): NewProviderToken
    {
        return new NewProviderToken(
            $token,
            new DateTimeImmutable(),
            (new DateTimeImmutable())->add(new DateInterval('PT28800M'))
        );
    }

    /**
     * @return NewProviderToken|null
     */
    public function getProviderRefreshToken(): ?NewProviderToken
    {
        return null;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * @param Configuration $configuration
     * @return void
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @return bool
     */
    public function isUpdateACLSupported(): bool
    {
        return true;
    }

    /**
     * @param array<string> $claims
     * @return array<int,AccessGroup>
     */
    public function getUserAccessGroupsFromClaims(array $claims): array
    {
        $userAccessGroups = [];
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $this->configuration->getCustomConfiguration();
        foreach ($customConfiguration->getACLConditions()->getRelations() as $authorizationRule) {
            $claimValue = $authorizationRule->getClaimValue();
            if (!in_array($claimValue, $claims)) {
                $this->info(
                    "Configured claim value not found in user claims",
                    ["claim_value" => $claimValue]
                );

                continue;
            }
            // We ensure here to not duplicate access group while using their id as index
            $userAccessGroups[$authorizationRule->getAccessGroup()->getId()] = $authorizationRule->getAccessGroup();
        }
        return $userAccessGroups;
    }

    /**
     * @return bool
     */
    public function canRefreshToken(): bool
    {
        return false;
    }

    /**
     * @param AuthenticationTokens $authenticationTokens
     * @return AuthenticationTokens|null
     */
    public function refreshToken(AuthenticationTokens $authenticationTokens): ?AuthenticationTokens
    {
        return null;
    }

    /**
     * @return ContactInterface|null
     */
    public function getAuthenticatedUser(): ?ContactInterface
    {
        return $this->authenticatedUser;
    }

    /**
     * @return array<string,mixed>
     */
    public function getUserInformation(): array
    {
        return [];
    }

    /**
     * @return ContactGroup[]
     */
    public function getUserContactGroups(): array
    {
        return [];
    }

    public function getIdTokenPayload(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws Throwable
     * @throws AssertionFailedException
     */
    private function createUser(): void
    {
        $customConfiguration = $this->configuration->getCustomConfiguration();
        $this->info('Auto import starting...', ["user" => $this->username]);
        $this->loginLogger->info(
            $this->configuration->getType(),
            'auto import starting...',
            ['user' => $this->username]
        );

        $usernameAttrs = $this->auth->getAttribute($customConfiguration->getUserNameBindAttribute());
        $emailAttrs = $this->auth->getAttribute($customConfiguration->getEmailBindAttribute());
        if (!isset($usernameAttrs[0]) || !isset($emailAttrs[0])) {
            throw InvalidArgumentProvidedException::create("invalid bind attributes provided for auto import");
        }
        $fullname = $usernameAttrs[0];
        $email = $emailAttrs[0];

        $alias = $this->username;
        $user = new NewUser($alias, $fullname, $email);
        $user->setContactTemplate($customConfiguration->getContactTemplate());
        $this->userRepository->create($user);
        $this->info('Auto import complete', [
            "user_alias" => $alias,
            "user_fullname" => $fullname,
            "user_email" => $email
        ]);
    }

    /**
     * @return string
     * @throws Error
     */
    public function login(): void
    {
        $auth = new Auth($this->formatter->format($this->configuration->getCustomConfiguration()));
        $auth->login();
    }

    /**
     * @return void
     * @throws Error
     */
    public function logout(): void
    {
        $returnTo = "/login";
        $parameters = [];
        $nameId = null;
        $sessionIndex = null;

        if (isset($_SESSION['saml']['samlNameId'])) {
            $nameId = $_SESSION['saml']['samlNameId'];
        }

        if (isset($_SESSION['saml']['samlSessionIndex'])) {
            $sessionIndex = $_SESSION['saml']['samlSessionIndex'];
        }

        $this->info('logout from SAML and redirect');
        $auth = new Auth($this->formatter->format($this->configuration->getCustomConfiguration()));
        $auth->logout($returnTo, $parameters, $nameId, $sessionIndex);
    }

    public function handleCallbackLogoutResponse(): void
    {
        $this->info("SAML SLS invoked");

        /** @var SAML $provider */
        $auth = new Auth($this->formatter->format($this->configuration->getCustomConfiguration()));
        if (isset($_SESSION) && isset($_SESSION['LogoutRequestID'])) {
            $requestID = $_SESSION['LogoutRequestID'];
        } else {
            $requestID = null;
        }

        $auth->processSLO(true, $requestID);

        // Avoid 'Open Redirect' attacks
        if (isset($_GET['RelayState']) && Utils::getSelfURL() != $_GET['RelayState']) {
            $auth->redirectTo($_GET['RelayState']);
            exit;
        }
    }
}
