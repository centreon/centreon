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

use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class Contactgroup
 */
class Contactgroup extends AbstractObject
{
    /** @var int */
    protected $use_cache = 1;

    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $cg_service_linked_cache = [];

    /** @var array */
    protected $cg_cache = [];

    /** @var null */
    protected $cg = null;

    /** @var string */
    protected $generate_filename = 'contactgroups.cfg';

    /** @var string */
    protected string $object_name = 'contactgroup';

    /** @var string */
    protected $attributes_select = '
        cg_id,
        cg_name as contactgroup_name,
        cg_alias as alias
    ';

    /** @var string[] */
    protected $attributes_write = ['contactgroup_name', 'alias'];

    /** @var string[] */
    protected $attributes_array = ['members'];

    /** @var null */
    protected $stmt_cg = null;

    /** @var null */
    protected $stmt_contact = null;

    /** @var null */
    protected $stmt_cg_service = null;

    /**
     * @throws PDOException
     * @return void
     */
    protected function getCgCache()
    {
        $stmt = $this->backend_instance->db->prepare("SELECT 
                    {$this->attributes_select}
                FROM contactgroup
                WHERE cg_activate = '1'
        ");
        $stmt->execute();
        $this->cg_cache = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * @see Contactgroup::$cg_service_linked_cache
     */
    private function getCgForServiceCache(): void
    {
        $stmt = $this->backend_instance->db->prepare("
            SELECT csr.contactgroup_cg_id, service_service_id
            FROM contactgroup_service_relation csr, contactgroup
            WHERE csr.contactgroup_cg_id = contactgroup.cg_id
            AND cg_activate = '1'
        ");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->cg_service_linked_cache[$value['service_service_id']])) {
                $this->cg_service_linked_cache[$value['service_service_id']][] = $value['contactgroup_cg_id'];
            } else {
                $this->cg_service_linked_cache[$value['service_service_id']] = [$value['contactgroup_cg_id']];
            }
        }
    }

    /**
     * @see Contactgroup::getCgCache()
     * @see Contactgroup::getCgForServiceCache()
     */
    protected function buildCache(): void
    {
        if ($this->done_cache == 1) {
            return;
        }

        $this->getCgCache();
        $this->getCgForServiceCache();
        $this->done_cache = 1;
    }

    /**
     * @param int $serviceId
     *
     * @throws PDOException
     * @return array
     */
    public function getCgForService(int $serviceId): array
    {
        $this->buildCache();

        // Get from the cache
        if (isset($this->cg_service_linked_cache[$serviceId])) {
            return $this->cg_service_linked_cache[$serviceId];
        }
        if ($this->done_cache == 1) {
            return [];
        }

        if (is_null($this->stmt_cg_service)) {
            $this->stmt_cg_service = $this->backend_instance->db->prepare("
                SELECT csr.contactgroup_cg_id
                FROM contactgroup_service_relation csr, contactgroup
                WHERE csr.service_service_id = :service_id
                AND csr.contactgroup_cg_id = contactgroup.cg_id
                AND cg_activate = '1'
            ");
        }

        $this->stmt_cg_service->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $this->stmt_cg_service->execute();
        $this->cg_service_linked_cache[$serviceId] = $this->stmt_cg_service->fetchAll(PDO::FETCH_COLUMN);

        return $this->cg_service_linked_cache[$serviceId];
    }

    /**
     * @param int $cgId
     *
     * @throws PDOException
     * @return array
     */
    public function getCgFromId(int $cgId): array
    {
        if (is_null($this->stmt_cg)) {
            $this->stmt_cg = $this->backend_instance->db->prepare("
                SELECT {$this->attributes_select}
                FROM contactgroup
                WHERE cg_id = :cg_id AND cg_activate = '1'
            ");
        }
        $this->stmt_cg->bindParam(':cg_id', $cgId, PDO::PARAM_INT);
        $this->stmt_cg->execute();
        $results = $this->stmt_cg->fetchAll(PDO::FETCH_ASSOC);
        $this->cg[$cgId] = array_pop($results);

        return $this->cg[$cgId];
    }

    /**
     * @param int $cgId
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function getContactFromCgId(int $cgId): void
    {
        if (! isset($this->cg[$cgId]['members_cache'])) {
            if (is_null($this->stmt_contact)) {
                $this->stmt_contact = $this->backend_instance->db->prepare('
                    SELECT contact_contact_id
                    FROM contactgroup_contact_relation
                    WHERE contactgroup_cg_id = :cg_id
                ');
            }
            $this->stmt_contact->bindParam(':cg_id', $cgId, PDO::PARAM_INT);
            $this->stmt_contact->execute();
            $this->cg[$cgId]['members_cache'] = $this->stmt_contact->fetchAll(PDO::FETCH_COLUMN);
        }

        $contact = Contact::getInstance($this->dependencyInjector);
        $this->cg[$cgId]['members'] = [];
        foreach ($this->cg[$cgId]['members_cache'] as $contact_id) {
            $member = $contact->generateFromContactId($contact_id);
            // Can have contact template in a contact group ???!!
            if (! is_null($member) && ! $contact->isTemplate($contact_id)) {
                $this->cg[$cgId]['members'][] = $member;
            }
        }
    }

    /**
     * @param int $cgId
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return string|null contactgroup_name
     */
    public function generateFromCgId(int $cgId): ?string
    {
        if (is_null($cgId)) {
            return null;
        }

        $this->buildCache();

        if ($this->use_cache == 1) {
            if (! isset($this->cg_cache[$cgId])) {
                return null;
            }
            $this->cg[$cgId] = &$this->cg_cache[$cgId];
        } elseif (! isset($this->cg[$cgId])) {
            $this->getCgFromId($cgId);
        }

        if (is_null($this->cg[$cgId])) {
            return null;
        }
        if ($this->checkGenerate($cgId)) {
            return $this->cg[$cgId]['contactgroup_name'];
        }

        $this->getContactFromCgId($cgId);

        $this->generateObjectInFile($this->cg[$cgId], $cgId);

        return $this->cg[$cgId]['contactgroup_name'];
    }
}
