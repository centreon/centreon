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

use Pimple\Container;

/**
 * Class
 *
 * @class HostCategory
 */
final class HostCategory extends AbstractObject
{
    private const TAG_TYPE = 'hostcategory';

    /** @var array<int,mixed> */
    private array $hostCategories = [];

    /** @var string */
    protected string $object_name = 'tag';

    /**
     * @param Container $dependencyInjector
     */
    protected function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->generate_filename = 'tags.cfg';
        $this->attributes_write =  [
            'id',
            'tag_name',
            'type',
        ];
    }

    /**
     * @param int $hostCategoryId
     *
     * @throws PDOException
     * @return self
     */
    private function addHostCategoryToList(int $hostCategoryId): self
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT hc_id as id, hc_name as tag_name
            FROM hostcategories
            WHERE hc_id = :hc_id
            AND level IS NULL
            AND hc_activate = '1'"
        );
        $stmt->bindParam(':hc_id', $hostCategoryId, PDO::PARAM_INT);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->hostCategories[$hostCategoryId] = $row;
            $this->hostCategories[$hostCategoryId]['members'] = [];
        }

        return $this;
    }

    /**
     * Add a host to members of a host category
     *
     * @param int $hostCategoryId
     * @param int $hostId
     * @param string $hostName
     *
     * @throws PDOException
     */
    public function insertHostToCategoryMembers(int $hostCategoryId, int $hostId, string $hostName): void
    {
        if (! isset($this->hostCategories[$hostCategoryId])) {
            $this->addHostCategoryToList($hostCategoryId);
        }
        if (
            isset($this->hostCategories[$hostCategoryId])
            && ! isset($this->hostCategories[$hostCategoryId]['members'][$hostId])
        ) {
            $this->hostCategories[$hostCategoryId]['members'][$hostId] = $hostName;
        }
    }

    /**
     * Write hostcategories in configuration file
     */
    public function generateObjects(): void
    {
        foreach ($this->hostCategories as $id => &$value) {
            if (! isset($value['members']) || count($value['members']) === 0) {
                continue;
            }
            $value['type'] = self::TAG_TYPE;

            $this->generateObjectInFile($value, $id);
        }
    }

    /**
     * Reset instance
     */
    public function reset(): void
    {
        parent::reset();
        foreach ($this->hostCategories as &$value) {
            if (! is_null($value)) {
                $value['members'] = [];
            }
        }
    }

    /**
     * @param int $hostId
     * @return int[]
     */
    public function getIdsByHostId(int $hostId): array
    {
        $hostCategoryIds = [];
        foreach ($this->hostCategories as $id => $value) {
            if (isset($value['members']) && in_array($hostId, array_keys($value['members']))) {
                $hostCategoryIds[] = (int) $id;
            }
        }

        return $hostCategoryIds;
    }
}
