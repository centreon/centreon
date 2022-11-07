<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Security\Domain\Authentication;

use Centreon\Domain\Log\LoggerTrait;
use Security\Domain\Authentication\Model\AuthenticationTokens;
use Centreon\Domain\Authentication\Exception\AuthenticationException;
use Security\Domain\Authentication\Interfaces\AuthenticationServiceInterface;
use Security\Domain\Authentication\Interfaces\AuthenticationRepositoryInterface;
use Security\Domain\Authentication\Interfaces\ProviderServiceInterface;
use Security\Domain\Authentication\Interfaces\SessionRepositoryInterface;
<<<<<<< HEAD
use Core\Application\Security\Repository\WriteTokenRepositoryInterface;
=======
>>>>>>> centreon/dev-21.10.x

/**
 * @package Security\Authentication
 */
class AuthenticationService implements AuthenticationServiceInterface
{
    use LoggerTrait;

    /**
     * @var AuthenticationRepositoryInterface
     */
    private $authenticationRepository;

    /**
     * @var ProviderServiceInterface
     */
    private $providerService;

    /**
     * @var SessionRepositoryInterface
     */
    private $sessionRepository;

    /**
<<<<<<< HEAD
     * @var WriteTokenRepositoryInterface
     */
    private $writeTokenRepository;

    /**
     * @param AuthenticationRepositoryInterface $authenticationRepository
     * @param ProviderServiceInterface $providerService
     * @param SessionRepositoryInterface $sessionRepository
     * @param WriteTokenRepositoryInterface $writeTokenRepository
=======
     * @param AuthenticationRepositoryInterface $authenticationRepository
     * @param ProviderServiceInterface $providerService
>>>>>>> centreon/dev-21.10.x
     */
    public function __construct(
        AuthenticationRepositoryInterface $authenticationRepository,
        ProviderServiceInterface $providerService,
<<<<<<< HEAD
        SessionRepositoryInterface $sessionRepository,
        WriteTokenRepositoryInterface $writeTokenRepository,
=======
        SessionRepositoryInterface $sessionRepository
>>>>>>> centreon/dev-21.10.x
    ) {
        $this->authenticationRepository = $authenticationRepository;
        $this->sessionRepository = $sessionRepository;
        $this->providerService = $providerService;
<<<<<<< HEAD
        $this->writeTokenRepository = $writeTokenRepository;
=======
>>>>>>> centreon/dev-21.10.x
    }

    /**
     * @inheritDoc
     */
    public function isValidToken(string $token): bool
    {
        $authenticationTokens = $this->findAuthenticationTokensByToken($token);
        if ($authenticationTokens === null) {
            $this->notice('[AUTHENTICATION SERVICE] token not found');
            return false;
        }

        $provider = $this->providerService->findProviderByConfigurationId(
            $authenticationTokens->getConfigurationProviderId()
        );

        if ($provider === null) {
            $this->notice('[AUTHENTICATION SERVICE] Provider not found');
            return false;
        }

        if ($authenticationTokens->getProviderToken()->isExpired()) {
            if (
                !$provider->canRefreshToken()
<<<<<<< HEAD
                || $authenticationTokens->getProviderRefreshToken() === null
                || $authenticationTokens->getProviderRefreshToken()->isExpired()
=======
                || ($authenticationTokens->getProviderRefreshToken() !== null
                && $authenticationTokens->getProviderRefreshToken()->isExpired())
>>>>>>> centreon/dev-21.10.x
            ) {
                $this->notice('Your session has expired');
                return false;
            }
            $newAuthenticationTokens = $provider->refreshToken($authenticationTokens);
            if ($newAuthenticationTokens === null) {
                $this->notice('Error while refresh token');
                return false;
            }
            $this->updateAuthenticationTokens($newAuthenticationTokens);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteSession(string $sessionToken): void
    {
        try {
            $this->authenticationRepository->deleteSecurityToken($sessionToken);
            $this->sessionRepository->deleteSession($sessionToken);
        } catch (\Exception $ex) {
            throw AuthenticationException::deleteSession($ex);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteExpiredSecurityTokens(): void
    {
        try {
<<<<<<< HEAD
            $this->writeTokenRepository->deleteExpiredSecurityTokens();
=======
            $this->authenticationRepository->deleteExpiredSecurityTokens();
>>>>>>> centreon/dev-21.10.x
        } catch (\Exception $ex) {
            throw AuthenticationException::deleteExpireToken($ex);
        }
    }

    /**
     * @inheritDoc
     */
    public function findAuthenticationTokensByToken(string $token): ?AuthenticationTokens
    {
        try {
            return $this->authenticationRepository->findAuthenticationTokensByToken($token);
        } catch (\Exception $ex) {
            throw AuthenticationException::findAuthenticationToken($ex);
        }
    }

    /**
     * @inheritDoc
     */
    public function updateAuthenticationTokens(AuthenticationTokens $authenticationTokens): void
    {
        try {
            $this->authenticationRepository->updateAuthenticationTokens($authenticationTokens);
        } catch (\Exception $ex) {
            throw AuthenticationException::updateAuthenticationTokens($ex);
        }
    }
}
