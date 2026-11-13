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

namespace Tests\App\Upgrade\Infrastructure\Double;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Serves each connection under its own name, as DoctrineBundle does.
 */
final readonly class NamedConnectionRegistry implements ConnectionRegistry
{
    /**
     * @param array<string, Connection> $connections
     */
    public function __construct(
        private array $connections,
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->connections[$name ?? 'default'] ?? throw new \InvalidArgumentException(sprintf('No "%s" connection.', $name));
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function getConnectionNames(): array
    {
        return array_combine(array_keys($this->connections), array_keys($this->connections));
    }
}
