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

namespace CentreonCommand\Domain\Entity;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * Command entity.
 *
 * @codeCoverageIgnore
 */
class Command
{
    public const SERIALIZER_GROUP_LIST = 'command-list';
    public const TABLE = 'command';
    public const TYPE_NOTIFICATION = 1;
    public const TYPE_CHECK = 2;
    public const TYPE_MISC = 3;
    public const TYPE_DISCOVERY = 4;

    /** @var int an identification of entity */
    #[Serializer\Groups([Command::SERIALIZER_GROUP_LIST])]
    private $id;

    /** @var string */
    #[Serializer\Groups([Command::SERIALIZER_GROUP_LIST])]
    private $name;

    /**
     * @param int $id
     */
    public function setId(?int $id = null): void
    {
        $this->id = $id;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param string $name
     */
    public function setName(?string $name = null): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Convert type from string to integer.
     *
     * @param string $name
     *
     * @return int|null
     */
    public static function getTypeIdFromName(?string $name = null): ?int
    {
        return match ($name) {
            'notification' => static::TYPE_NOTIFICATION,
            'check' => static::TYPE_CHECK,
            'misc' => static::TYPE_MISC,
            'discovery' => static::TYPE_DISCOVERY,
            default => null,
        };
    }
}
