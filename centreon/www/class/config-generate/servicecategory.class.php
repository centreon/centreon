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
 * @class ServiceCategory
 */
final class ServiceCategory extends AbstractObject
{
    private const TAG_TYPE = 'servicecategory';

    /** @var array<int,mixed> */
    private array $serviceCategories = [];

    /** @var array<int,int[]>|null */
    private array|null $serviceCategoriesRelationsCache = null;

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
     * Build cache for service categories
     */
    private function buildCache(): void
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT service_categories.sc_id, service_service_id
            FROM service_categories, service_categories_relation
            WHERE level IS NULL
            AND sc_activate = '1'
            AND service_categories_relation.sc_id = service_categories.sc_id"
        );
        $stmt->execute();

        $this->serviceCategoriesRelationsCache = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $this->serviceCategoriesRelationsCache[(int) $value['service_service_id']][] = (int) $value['sc_id'];
        }
    }

    /**
     * Get service categories linked to service
     *
     * @param int $serviceId
     * @return int[]
     */
    public function getServiceCategoriesByServiceId(int $serviceId): array
    {
        if ($this->serviceCategoriesRelationsCache === null) {
            $this->buildCache();
        }

        return $this->serviceCategoriesRelationsCache[$serviceId] ?? [];
    }

    /**
     * Retrieve a categorie from its id
     *
     * @param int $serviceCategoryId
     *
     * @throws PDOException
     * @return self
     */
    private function addServiceCategoryToList(int $serviceCategoryId): self
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT sc_id as id, sc_name as tag_name
            FROM service_categories
            WHERE sc_id = :serviceCategoryId AND level IS NULL AND sc_activate = '1'"
        );
        $stmt->bindParam(':serviceCategoryId', $serviceCategoryId, PDO::PARAM_INT);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->serviceCategories[$serviceCategoryId] = $row;
            $this->serviceCategories[$serviceCategoryId]['members'] = [];
        }

        return $this;
    }

    /**
     * Add a service to members of a service category
     *
     * @param int $serviceCategoryId
     * @param int $serviceId
     * @param string $serviceDescription
     *
     * @throws PDOException
     */
    public function insertServiceToServiceCategoryMembers(
        int $serviceCategoryId,
        int $serviceId,
        string $serviceDescription
    ): void {
        if (! isset($this->serviceCategories[$serviceCategoryId])) {
            $this->addServiceCategoryToList($serviceCategoryId);
        }
        if (
            isset($this->serviceCategories[$serviceCategoryId])
            && ! isset($this->serviceCategories[$serviceCategoryId]['members'][$serviceId])
        ) {
            $this->serviceCategories[$serviceCategoryId]['members'][$serviceId] = $serviceDescription;
        }
    }

    /**
     * Write servicecategories in configuration file
     */
    public function generateObjects(): void
    {
        foreach ($this->serviceCategories as $id => &$value) {
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
        foreach ($this->serviceCategories as &$value) {
            if (! is_null($value)) {
                $value['members'] = [];
            }
        }
    }

    /**
     * @param int $serviceId
     * @return int[]
     */
    public function getIdsByServiceId(int $serviceId): array
    {
        $serviceCategoryIds = [];
        foreach ($this->serviceCategories as $id => $value) {
            if (isset($value['members']) && in_array($serviceId, array_keys($value['members']))) {
                $serviceCategoryIds[] = (int) $id;
            }
        }

        return $serviceCategoryIds;
    }
}
