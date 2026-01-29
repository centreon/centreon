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
 * @class MetaHost
 */
class MetaHost extends AbstractObject
{
    /** @var string */
    protected $generate_filename = 'meta_host.cfg';

    /** @var string */
    protected string $object_name = 'host';

    /** @var string[] */
    protected $attributes_write = ['host_name', 'alias', 'address', 'check_command', 'max_check_attempts', 'check_interval', 'active_checks_enabled', 'passive_checks_enabled', 'check_period', 'notification_interval', 'notification_period', 'notification_options', 'notifications_enabled', 'register'];

    /** @var string[] */
    protected $attributes_hash = ['macros'];

    /**
     * @param $host_name
     *
     * @throws PDOException
     * @return mixed|null
     */
    public function getHostIdByHostName($host_name)
    {
        $stmt = $this->backend_instance->db->prepare('SELECT 
              host_id
            FROM host
            WHERE host_name = :host_name
            ');
        $stmt->bindParam(':host_name', $host_name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_pop($result);
    }

    /**
     * @param $host_id
     *
     * @throws Exception
     * @return int|void
     */
    public function generateObject($host_id)
    {
        if ($this->checkGenerate($host_id)) {
            return 0;
        }

        $object = [];
        $object['host_name'] = '_Module_Meta';
        $object['alias'] = 'Meta Service Calculate Module For Centreon';
        $object['address'] = '127.0.0.1';
        $object['check_command'] = 'check_meta_host_alive';
        $object['max_check_attempts'] = 3;
        $object['check_interval'] = 1;
        $object['active_checks_enabled'] = 0;
        $object['passive_checks_enabled'] = 0;
        $object['check_period'] = 'meta_timeperiod';
        $object['notification_interval'] = 60;
        $object['notification_period'] = 'meta_timeperiod';
        $object['notification_period'] = 'meta_timeperiod';
        $object['notification_options'] = 'd';
        $object['notifications_enabled'] = 0;
        $object['register'] = 1;
        $object['macros'] = ['_HOST_ID' => $host_id];

        $this->generateObjectInFile($object, $host_id);
    }
}
