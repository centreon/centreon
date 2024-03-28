<?php

/**
 * Copyright 2021 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Class Export
 */
class Export
{
    /** @var array<mixed> */
    protected $bash = [];
    /** @var string */
    protected $user = '';
    /** @var string */
    protected $pwd = '';
    /** @var string */
    protected $tmpFile = '';
    /** @var string */
    protected $tmpName = '';
    /** @var CentreonDB */
    protected $db;
    /** @var ClapiObject */
    protected $clapiConnector;

    /**
     * Export constructor.
     * @param ClapiObject $clapiConnector
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct($clapiConnector, $dependencyInjector)
    {
        $this->db = $dependencyInjector['configuration_db'];
        $this->clapiConnector = $clapiConnector;
        $this->tmpName = 'centreon-clapi-export-' . time();
        $this->tmpFile = '/tmp/' . $this->tmpName . '.txt';
    }

    /**
     * @param string $type
     * @return array{
     *     result: string,
     *     error: string,
     * }
     */
    private function generateCmd($type)
    {
        $cmdScript = ['result' => '', 'error' => ''];
        $cmdTypeRelation = [
            'n' => 1,
            'c' => 2,
            'm' => 3,
            'd' => 4
        ];
        $query = 'SELECT `command_name` FROM `command`WHERE `command_type` =' . $cmdTypeRelation[$type];
        $res = $this->db->query($query);

        while ($row = $res->fetch()) {
            $result = $this->generateObject('CMD', ';' . $row['command_name']);
            $cmdScript['result'] .= $result['result'];
            $cmdScript['error'] .= $result['error'];
        }
        return $cmdScript;
    }

    /**
     * @param array<mixed> $value
     * @return array{
     *     result: string,
     *     error: string,
     * }
     */
    private function generateInstance($value)
    {
        $cmdScript = array();

        if (isset($value['INSTANCE'])) {
            //export instance
            $result = $this->generateObject('INSTANCE');
            $cmdScript['result'] = $result['result'];
            $cmdScript['error'] = $result['error'];

            //export resource cfg
            $result = $this->generateObject('RESOURCECFG');
            $cmdScript['result'] .= $result['result'];
            $cmdScript['error'] .= $result['error'];

            //export broker cfg
            $result = $this->generateObject('CENTBROKERCFG');
            $cmdScript['result'] .= $result['result'];
            $cmdScript['error'] .= $result['error'];

            //export engine cfg
            $result = $this->generateObject('ENGINECFG');
            $cmdScript['result'] .= $result['result'];
            $cmdScript['error'] .= $result['error'];
        } elseif (!empty($value['INSTANCE_filter'])) {
            $query = 'SELECT `id` FROM `nagios_server` WHERE `name` = "' . $value['INSTANCE_filter'] . '"';
            $res = $this->db->query($query);
            $pollerId = null;
            while ($row = $res->fetch()) {
                $pollerId = $row['id'];
            }

            //export instance
            $filter = ';' . $value['INSTANCE_filter'];
            $result = $this->generateObject('INSTANCE', $filter);
            $cmdScript['result'] = $result['result'];
            $cmdScript['error'] = $result['error'];

            // check is missed pollerId
            if ($pollerId === null) {
                return $cmdScript;
            }

            //export resource cfg
            $query = 'SELECT r.resource_name FROM cfg_resource r, cfg_resource_instance_relations cr '
                . 'WHERE cr.instance_id =' . $pollerId
                . ' AND cr.resource_id = r.resource_id';

            $res = $this->db->query($query);
            while ($row = $res->fetch()) {
                $filter = ';' . $row['resource_name'];
                $result = $this->generateObject('RESOURCECFG', $filter);
                $cmdScript['result'] .= $result['result'];
                $cmdScript['error'] .= $result['error'];
            }

            //export broker cfg
            $query = 'SELECT b.config_name FROM cfg_centreonbroker b WHERE b.ns_nagios_server =' . $pollerId;
            $res = $this->db->query($query);
            while ($row = $res->fetch()) {
                $filter = ';' . $row['config_name'];
                $result = $this->generateObject('CENTBROKERCFG', $filter);
                $cmdScript['result'] .= $result['result'];
                $cmdScript['error'] .= $result['error'];
            }

            //export engine cfg
            $query = 'SELECT n.nagios_name FROM cfg_nagios n WHERE n.nagios_server_id =' . $pollerId;
            $res = $this->db->query($query);
            while ($row = $res->fetch()) {
                $filter = ';' . $row['nagios_name'];
                $result = $this->generateObject('ENGINECFG', $filter);
                $cmdScript['result'] .= $result['result'];
                $cmdScript['error'] .= $result['error'];
            }
        }

        return $cmdScript;
    }

    /**
     * @param string $object
     * @param array<mixed> $value
     * @return array{
     *     result: string,
     *     error: string,
     * }|null
     */
    public function generateGroup($object, $value)
    {
        if ($object == 'cmd') {
            foreach ($value as $cmdType => $val) {
                $type = explode('_', $cmdType);
                return $this->generateCmd($type[0]);
            }
        } elseif ($object == 'INSTANCE') {
            return $this->generateInstance($value);
        } elseif (isset($value[$object])) {
            return $this->generateObject($object);
        } elseif (!empty($value[$object . '_filter'])) {
            $filter = ';' . $value[$object . '_filter'];
            return $this->generateObject($object, $filter);
        }

        return null;
    }

    /**
     * @param string $object
     * @param string $filter
     * @return array{
     *     result: string,
     *     error: string,
     * }
     */
    public function generateObject($object, $filter = '')
    {
        $content = ['result' => '', 'error' => ''];
        $result = '';
        if ($object == 'ACL') {
            $acl = $this->generateAcl();
            $result .= $acl['result'];
        } else {
            ob_start();
            $option = $object . $filter;
            $this->clapiConnector->addClapiParameter('select', $option);
            try {
                $this->clapiConnector->export(true);
                $result .= ob_get_contents();
                ob_end_clean();
            } catch (\Exception $e) {
                $result .= $e->getMessage();
                ob_end_clean();
            }
        }

        if (preg_match("#Unknown object#i", $result)) {
            $content['error'] = $result;
        } else {
            $content['result'] = $result;
        }

        return $content;
    }

    /**
     * @return array{
     *     result: string,
     *     error: string,
     * }
     */
    private function generateAcl()
    {
        $aclScript = [
            'result' => '',
            'error' => '',
        ];
        $oAcl = ['ACLMENU', 'ACLACTION', 'ACLRESOURCE', 'ACLGROUP'];
        foreach ($oAcl as $acl) {
            $result = $this->generateObject($acl);
            $aclScript['result'] .= $result['result'];
            $aclScript['error'] .= $result['error'];
        }

        return $aclScript;
    }

    /**
     * @param array<string> $content
     * @return string
     */
    public function clapiExport($content)
    {
        $fp = fopen($this->tmpFile, 'w');
        foreach ($content as $command) {
            fwrite($fp, utf8_encode($command));
        }
        fclose($fp);
        return $this->tmpName;
    }
}
