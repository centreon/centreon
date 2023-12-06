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

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class NewPlaylist
{
    public const NAME_MIN_LENGTH = 1;
    public const NAME_MAX_LENGTH = 255;
    public const DESCRIPTION_MIN_LENGTH = 1;
    public const DESCRIPTION_MAX_LENGTH = 65535;
    public const MINIMUM_ROTATION_TIME = 10; // time in seconds
    public const MAXIMUM_ROTATION_TIME = 60; // time in seconds

    private ?string $description = null;

    private \DateTimeImmutable $createdAt;

    /**
     * @param string $name
     * @param int $rotationTime
     * @param bool $isPublic
     * @param int $authorId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly string $name,
        private readonly int $rotationTime,
        private readonly bool $isPublic,
        private readonly int $authorId
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

    public function getAuthorId(): int
    {
        return $this->authorId;
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
}
