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

namespace CentreonNotification\Domain\Entity;

use Centreon\Infrastructure\CentreonLegacyDB\Mapping;
use PDO;
use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * Escalation entity.
 *
 * @codeCoverageIgnore
 */
class Escalation implements Mapping\MetadataInterface
{
    public const SERIALIZER_GROUP_LIST = 'escalation-list';

    /**
     * Use class metadata instead calling of this constant.
     *
     * <example>
     * $this->repository->getClassMetadata()->getTableName()
     * </example>
     */
    public const TABLE = 'escalation',
                 ENTITY_IDENTIFICATOR_COLUMN = 'esc_id';

    /**
     * @var int an identification of entity
     */
    #[Serializer\Groups([Escalation::SERIALIZER_GROUP_LIST])]
    private $id;

    /**
     * @var string escalation name
     */
    #[Serializer\Groups([Escalation::SERIALIZER_GROUP_LIST])]
    private $name;

    /**
     * {@inheritDoc}
     */
    public static function loadMetadata(Mapping\ClassMetadata $metadata): void
    {
        $metadata->setTableName(static::TABLE)
            ->add('id', 'esc_id', PDO::PARAM_INT, null, true)
            ->add('name', 'esc_name', PDO::PARAM_STR);
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
}
