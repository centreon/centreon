<?php
/**
 * Copyright 2018 Centreon
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
    protected $bash = array();
    protected $user = '';
    protected $pwd = '';
    protected $tmpFile = '';
    protected $tmpName = '';
    protected $db;
    protected $clapiConnector;

    /**
     * Export constructor.
     * @param $clapiConnector
     */
    public function __construct($clapiConnector)
    {
        $this->db = new \CentreonDB();
        $this->clapiConnector = $clapiConnector;
        $this->tmpName = 'centreon-clapi-export-' . time();
        $this->tmpFile = '/tmp/' . $this->tmpName . '.txt';
    }

    /**
     * @param $type
     * @return string
     */
    private function generateCmd($type)
    {
        $cmdScript = '';
        $cmdTypeRelation = array(
            'n' => 1,
            'c' => 2,
            'm' => 3,
            'd' => 4
        );
        $query = 'SELECT `command_name` FROM `command`WHERE `command_type` =' . $cmdTypeRelation[$type];
        $res = $this->db->query($query);

        while ($row = $res->fetchRow()) {
            $cmdScript .= $this->generateObject('CMD', ';' . $row['command_name']);
        }
        return $cmdScript;
    }

    /**
     * @param $object
     * @param $value
     * @return string
     */
    public function generateGroup($object, $value)
    {
        if ($object == 'cmd') {
            foreach ($value as $cmdType => $val) {
                $type = explode('_', $cmdType);
                return $this->generateCmd($type[0]);
            }
        } else {
            if (isset($value[$object])) {
                return $this->generateObject($object);
            } elseif (!empty($value[$object . '_filter'])) {
                $filter = ';' . $value[$object . '_filter'];
                return $this->generateObject($object, $filter);
            }
        }
    }

    /**
     * @param $object
     * @param string $filter
     * @return string
     */
    public function generateObject($object, $filter = '')
    {
        $content = array();
        $result = '';
        if ($object == 'ACL') {
            $this->generateAcl();
        } else {
            ob_start();
            $option = $object . $filter;
            $this->clapiConnector->addClapiParameter('select', $option);
            try {
                $this->clapiConnector->export();
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
     * @return string
     */
    private function generateAcl()
    {
        $aclScript = '';
        $oAcl = array('ACLMENU', 'ACLACTION', 'ACLRESOURCE', 'ACLGROUP');
        foreach ($oAcl as $acl) {
            $aclScript .= $this->generateObject($acl);
        }
        return $aclScript;
    }

    /**
     * @param $content
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
