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

namespace Core\Security\User\Infrastructure\Repository;

use App\Shared\Infrastructure\TransformerInterface;
use Core\Security\User\Domain\Model\User;
use Core\Security\User\Domain\Model\UserPassword;

/**
 * @implements TransformerInterface<array<string, mixed>, User>
 */
class DbUserTransformer implements TransformerInterface
{
    /**
     * @param array<string, mixed> $from
     *
     * @throws \InvalidArgumentException
     * @return User
     */
    public function transform(mixed $from): User
    {
        if (! is_array($from) || $from === []) {
            throw new \InvalidArgumentException('Cannot transform empty record to User');
        }

        if (! isset($from['passwords']) || ! is_array($from['passwords']) || $from['passwords'] === []) {
            throw new \InvalidArgumentException('Cannot transform record to User without passwords');
        }

        $loginAttempts = isset($from['login_attempts']) ? (int) $from['login_attempts'] : null;
        $blockingTime = isset($from['blocking_time'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $from['blocking_time'])
            : null;

        $passwords = [];
        foreach ($from['passwords'] as $password) {
            $passwords[] = new UserPassword(
                (int) $from['contact_id'],
                $password['password'],
                (new \DateTimeImmutable())->setTimestamp((int) $password['creation_date']),
            );
        }

        return new User(
            (int) $from['contact_id'],
            $from['contact_alias'],
            $passwords,
            end($passwords),
            $loginAttempts,
            $blockingTime,
        );
    }
}
