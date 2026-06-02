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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use Doctrine\DBAL\Connection;

final readonly class DbalPollerTokenRepository implements PollerTokenRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function getFirstValidPollerToken(): string
    {
        $result = $this->connection->fetchOne(
            <<<'SQL'
                SELECT token_string
                FROM authentication_tokens
                WHERE type = 'poller'
                  AND is_revoked = 0
                  AND (expiration_date IS NULL OR expiration_date > UNIX_TIMESTAMP())
                ORDER BY creation_date ASC
                LIMIT 1
                SQL
        );

        if (! $result) {
            throw new PollerTokenNotFoundException([], 'No valid poller token found.');
        }

        return $result;
    }
}
