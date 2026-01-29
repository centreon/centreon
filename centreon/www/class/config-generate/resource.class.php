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

/**
 * Class
 *
 * @class Resource
 */
class Resource extends AbstractObject
{
    /** @var string */
    protected $generate_filename = 'resource.cfg';

    /** @var string */
    protected string $object_name;

    /** @var null */
    protected $stmt = null;

    /** @var string[] */
    protected $attributes_hash = ['resources'];

    /** @var null */
    private $connectors = null;

    /**
     * @param $poller_id
     *
     * @throws PDOException
     * @return int|void
     */
    public function generateFromPollerId($poller_id)
    {
        if (is_null($poller_id)) {
            return 0;
        }

        if (is_null($this->stmt)) {
            $query = 'SELECT resource_name, resource_line FROM cfg_resource_instance_relations, cfg_resource '
                . 'WHERE instance_id = :poller_id AND cfg_resource_instance_relations.resource_id = '
                . "cfg_resource.resource_id AND cfg_resource.resource_activate = '1'";
            $this->stmt = $this->backend_instance->db->prepare($query);
        }
        $this->stmt->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $this->stmt->execute();

        $object = ['resources' => []];
        foreach ($this->stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $object['resources'][$value['resource_name']] = $value['resource_line'];
        }

        $this->generateFile($object);
    }
}
