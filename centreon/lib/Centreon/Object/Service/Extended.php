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

require_once 'Centreon/Object/Object.php';

/**
 * Used for interacting with Service extended information
 *
 * @author sylvestre
 */
class Centreon_Object_Service_Extended extends Centreon_Object
{
    protected $table = 'extended_service_information';

    protected $primaryKey = 'service_service_id';

    protected $uniqueLabelField = 'service_service_id';

    /**
     * Used for inserting object into database
     *
     * @param array $params
     * @return int
     */
    public function insert($params = [])
    {
        $sql = "INSERT INTO {$this->table} ";
        $sqlFields = '';
        $sqlValues = '';
        $sqlParams = [];
        foreach ($params as $key => $value) {
            if ($sqlFields != '') {
                $sqlFields .= ',';
            }
            if ($sqlValues != '') {
                $sqlValues .= ',';
            }
            $sqlFields .= $key;
            $sqlValues .= '?';
            $sqlParams[] = $value;
        }
        if ($sqlFields && $sqlValues) {
            $sql .= '(' . $sqlFields . ') VALUES (' . $sqlValues . ')';
            $this->db->query($sql, $sqlParams);

            return $this->db->lastInsertId();
        }

        return null;
    }

    /**
     * Get object parameters
     *
     * @param int $objectId
     * @param mixed $parameterNames
     * @return array
     */
    public function getParameters($objectId, $parameterNames)
    {
        $params = parent::getParameters($objectId, $parameterNames);
        $params_image = ['esi_icon_image'];
        if (! is_array($params)) {
            return [];
        }
        foreach ($params_image as $image) {
            if (array_key_exists($image, $params)) {
                $sql = 'SELECT dir_name, img_path
                        FROM view_img vi
                        LEFT JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id
                        LEFT JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
                        WHERE img_id = ?';
                $res = $this->getResult($sql, [$params[$image]], 'fetch');
                if (is_array($res)) {
                    $params[$image] = $res['dir_name'] . '/' . $res['img_path'];
                }
            }
        }

        return $params;
    }
}
