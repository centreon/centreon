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

namespace Core\Notification\Infrastructure\Repository;

use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;

class NotificationResourceRepositoryProvider implements NotificationResourceRepositoryProviderInterface
{
    /** @var non-empty-array<NotificationResourceRepositoryInterface> */
    private array $repositories;

    /**
     * @param NotificationResourceRepositoryInterface[] $repositories
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        iterable $repositories
    ) {
        $reposAsArray = is_array($repositories) ? $repositories : iterator_to_array($repositories);
        if ($reposAsArray === []) {
            throw new \InvalidArgumentException('There must be at least one notification resource provider');
        }
        $this->repositories = $reposAsArray;
    }

    /**
     * Return the repository matching the provided type.
     *
     * @param string $type
     *
     * @throws \Throwable
     *
     * @return NotificationResourceRepositoryInterface
     */
    public function getRepository(string $type): NotificationResourceRepositoryInterface
    {
        foreach ($this->repositories as $provider) {
            if ($provider->supportResourceType($type)) {
                return $provider;
            }
        }

        throw NotificationException::invalidResourceType();
    }

    /**
     * Return all resource repositories.
     *
     * @throws \Throwable
     *
     * @return non-empty-array<NotificationResourceRepositoryInterface>
     */
    public function getRepositories(): array
    {
        return $this->repositories;
    }
}
