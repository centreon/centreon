<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Domain\Common\GeoCoords;

class NewHostGroup
{
    public const MAX_NAME_LENGTH = 200;
    public const MIN_NAME_LENGTH = 1;
    public const MAX_ALIAS_LENGTH = 200;
    public const MAX_NOTES_LENGTH = 200;
    public const MAX_NOTES_URL_LENGTH = 255;
    public const MAX_ACTION_URL_LENGTH = 255;
    public const MAX_COMMENT_LENGTH = 65535;

    /**
     * @param string $name
     * @param string $alias
     * @param string $notes
     * @param string $notesUrl
     * @param string $actionUrl
     * @param positive-int|null $iconId FK
     * @param positive-int|null $iconMapId FK
     * @param int|null $rrdRetention Days
     * @param GeoCoords|null $geoCoords
     * @param string $comment
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected string $alias = '',
        protected string $notes = '',
        protected string $notesUrl = '',
        protected string $actionUrl = '',
        protected ?int $iconId = null,
        protected ?int $iconMapId = null,
        protected ?int $rrdRetention = null,
        protected null|GeoCoords $geoCoords = null,
        protected string $comment = '',
        protected bool $isActivated = true,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = trim($this->name);
        Assertion::minLength($this->name, self::MIN_NAME_LENGTH, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");

        $this->alias = trim($this->alias);
        Assertion::maxLength($this->alias, self::MAX_ALIAS_LENGTH, "{$shortName}::alias");

        $this->notes = trim($this->notes);
        Assertion::maxLength($this->notes, self::MAX_NOTES_LENGTH, "{$shortName}::notes");

        $this->notesUrl = trim($this->notesUrl);
        Assertion::maxLength($this->notesUrl, self::MAX_NOTES_URL_LENGTH, "{$shortName}::notesUrl");

        $this->actionUrl = trim($this->actionUrl);
        Assertion::maxLength($this->actionUrl, self::MAX_ACTION_URL_LENGTH, "{$shortName}::actionUrl");

        $this->comment = trim($this->comment);
        Assertion::maxLength($this->comment, self::MAX_COMMENT_LENGTH, "{$shortName}::comment");

        if (null !== $iconId) {
            Assertion::positiveInt($iconId, "{$shortName}::iconId");
        }

        if (null !== $iconMapId) {
            Assertion::positiveInt($iconMapId, "{$shortName}::iconMapId");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function getNotesUrl(): string
    {
        return $this->notesUrl;
    }

    public function getActionUrl(): string
    {
        return $this->actionUrl;
    }

    public function getIconId(): ?int
    {
        return $this->iconId;
    }

    public function getIconMapId(): ?int
    {
        return $this->iconMapId;
    }

    public function getRrdRetention(): ?int
    {
        return $this->rrdRetention;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function getGeoCoords(): ?GeoCoords
    {
        return $this->geoCoords;
    }
}
