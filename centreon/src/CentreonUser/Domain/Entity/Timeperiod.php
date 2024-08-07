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

namespace CentreonUser\Domain\Entity;

use Centreon\Infrastructure\CentreonLegacyDB\Mapping;
use PDO;
use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * Timeperiod entity.
 *
 * @codeCoverageIgnore
 */
class Timeperiod implements Mapping\MetadataInterface
{
    public const SERIALIZER_GROUP_LIST = 'timeperiod-list';
    public const TABLE = 'timeperiod';
    public const ENTITY_IDENTIFICATOR_COLUMN = 'tp_id';

    /** @var int an identification of entity */
    #[Serializer\Groups([Timeperiod::SERIALIZER_GROUP_LIST])]
    private $id;

    /** @var string */
    #[Serializer\Groups([Timeperiod::SERIALIZER_GROUP_LIST])]
    private $name;

    /** @var string */
    #[Serializer\Groups([Timeperiod::SERIALIZER_GROUP_LIST])]
    private $alias;

    /**
     * {@inheritDoc}
     */
    public static function loadMetadata(Mapping\ClassMetadata $metadata): void
    {
        $metadata->setTableName(static::TABLE)
            ->add('id', 'tp_id', PDO::PARAM_INT, null, true)
            ->add('name', 'tp_name', PDO::PARAM_STR)
            ->add('alias', 'tp_alias', PDO::PARAM_STR);
    }

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
     * @param string $alias
     */
    public function setAlias(?string $alias = null): void
    {
        $this->alias = $alias;
    }

    /**
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
