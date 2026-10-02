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

namespace Tests\Core\Common\Infrastructure;

use Centreon\Domain\Contact\Contact;
use Core\Common\Infrastructure\CurrentUserIdProvider;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

beforeEach(function (): void {
    $this->tokenStorage = new TokenStorage();
    $this->provider = new CurrentUserIdProvider($this->tokenStorage);
    $this->previousSession = $_SESSION ?? null;
    $_SESSION = [];
});

afterEach(function (): void {
    $_SESSION = $this->previousSession;
});

function legacySession(int $userId): object
{
    $legacyUser = (new \ReflectionClass(\CentreonUser::class))->newInstanceWithoutConstructor();
    $legacyUser->user_id = $userId;

    return (object) ['user' => $legacyUser];
}

it('should return the user of the Symfony token', function (): void {
    $contact = (new Contact())->setId(12);
    $this->tokenStorage->setToken(new PreAuthenticatedToken($contact, 'api', []));
    $_SESSION['centreon'] = legacySession(34);

    expect($this->provider->getUserId())->toBe(12);
});

it('should fall back to the user of the legacy session', function (): void {
    $_SESSION['centreon'] = legacySession(34);

    expect($this->provider->getUserId())->toBe(34);
});

it('should return null when no user can be identified', function (): void {
    expect($this->provider->getUserId())->toBeNull();
});
