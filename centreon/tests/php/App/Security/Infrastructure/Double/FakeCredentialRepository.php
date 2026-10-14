<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\Security\Infrastructure\Double;

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Exception\CredentialNotFoundException;
use App\Security\Domain\Repository\CredentialRepository;

/**
 * Holds users registered with {@see addUser()}, looked up by id or username. Tokens and sessions are
 * not modelled: those lookups always fail.
 */
final class FakeCredentialRepository implements CredentialRepository
{
    /** @var array<int, Credential> */
    private array $credentials = [];

    /**
     * Registers an active user, either unrestricted (a super admin) or scoped by ACL.
     */
    public function addUser(UserId $id, bool $unrestricted): void
    {
        $this->credentials[$id->value] = $unrestricted ? TestCredentials::unrestricted($id->value) : TestCredentials::restricted($id->value);
    }

    public function getByToken(string $token): Credential
    {
        throw new CredentialNotFoundException(['token' => $token]);
    }

    public function getBySession(string $sessionId): Credential
    {
        throw new CredentialNotFoundException(['sessionId' => $sessionId]);
    }

    public function getByUsername(string $username): Credential
    {
        foreach ($this->credentials as $credential) {
            if ($credential->identifier->value === $username) {
                return $credential;
            }
        }

        throw new CredentialNotFoundException(['username' => $username]);
    }

    public function getById(UserId $id): Credential
    {
        return $this->credentials[$id->value] ?? throw new CredentialNotFoundException(['id' => $id->value]);
    }
}
