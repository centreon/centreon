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

namespace Core\Dashboard\Playlist\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Core\Dashboard\Playlist\Domain\Exception\NewPlaylistException;

class NewPlaylist
{
    public const NAME_MIN_LENGTH = 1;
    public const NAME_MAX_LENGTH = 255;
    public const DESCRIPTION_MIN_LENGTH = 1;
    public const DESCRIPTION_MAX_LENGTH = 65535;
    public const MINIMUM_ROTATION_TIME = 10; // time in seconds
    public const MAXIMUM_ROTATION_TIME = 60; // time in seconds

    /** @var DashboardOrder[] */
    protected array $dashboardsOrder = [];

    protected ?string $description = null;

    protected \DateTimeImmutable $createdAt;

    /** If the author has been deleted, the author is null but the playlist is still accessible*/
    protected ?PlaylistAuthor $author = null;

    /**
     * @param string $name
     * @param int $rotationTime
     * @param bool $isPublic
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected int $rotationTime,
        protected bool $isPublic
    ) {
        Assertion::minLength($name, self::NAME_MIN_LENGTH, 'NewPlaylist::name');
        Assertion::maxLength($name, self::NAME_MAX_LENGTH, 'NewPlaylist::name');
        Assertion::range(
            $rotationTime,
            self::MINIMUM_ROTATION_TIME,
            self::MAXIMUM_ROTATION_TIME,
            'NewPlaylist::rotationTime'
        );

        $this->createdAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRotationTime(): int
    {
        return $this->rotationTime;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAuthor(): ?PlaylistAuthor
    {
        return $this->author;
    }

    public function setAuthor(?PlaylistAuthor $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return DashboardOrder[]
     */
    public function getDashboardsOrder(): array
    {
        return $this->dashboardsOrder;
    }

    /**
     * @param DashboardOrder[] $dashboardsOrder
     *
     * @throws NewPlaylistException
     *
     * @return self
     */
    public function setDashboardsOrder(array $dashboardsOrder): self
    {
        $this->dashboardsOrder = [];

        foreach ($dashboardsOrder as $dashboardOrder) {
            $this->addDashboardsOrder($dashboardOrder);
        }

        return $this;
    }

    /**
     * @param DashboardOrder $dashboardOrder
     *
     * @throws NewPlaylistException
     *
     * @return self
     */
    public function addDashboardsOrder(DashboardOrder $dashboardOrder): self
    {
        $this->validateDashboardOrder($dashboardOrder);
        $this->dashboardsOrder[] = $dashboardOrder;

        return $this;
    }

    /**
     * @param string|null $description
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        if (is_string($description)) {
            Assertion::minLength($description, self::DESCRIPTION_MIN_LENGTH, 'NewPlaylist::description');
            Assertion::maxLength($description, self::DESCRIPTION_MAX_LENGTH, 'NewPlaylist::description');
        }

        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param DashboardOrder $dashboardOrder
     *
     * @throws NewPlaylistException
     */
    private function validateDashboardOrder(DashboardOrder $dashboardOrder): void
    {
        foreach ($this->dashboardsOrder as $existingDashboardOrder) {
            if ($existingDashboardOrder->getOrder() === $dashboardOrder->getOrder()) {
                throw NewPlaylistException::orderMustBeUnique();
            }
        }
    }
}