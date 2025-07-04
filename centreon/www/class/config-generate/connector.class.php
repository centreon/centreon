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

class Connector extends AbstractObject
{
    private $connectors = null;

    protected $generate_filename = 'connectors.cfg';

    protected string $object_name = 'connector';

    protected $attributes_select = '
        id,
        name as connector_name,
        command_line as connector_line
    ';

    protected $attributes_write = ['connector_name', 'connector_line'];

    private function getConnectors(): void
    {
        $stmt = $this->backend_instance->db->prepare("SELECT 
              {$this->attributes_select}
            FROM connector 
                WHERE enabled = '1'
            ");
        $stmt->execute();
        $this->connectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateObjects($connector_path)
    {
        if (is_null($connector_path)) {
            return 0;
        }

        $this->getConnectors();
        foreach ($this->connectors as $connector) {
            $connector['connector_line'] = $connector_path . '/' . $connector['connector_line'];
            $this->generateObjectInFile($connector, $connector['id']);
        }
    }
}
