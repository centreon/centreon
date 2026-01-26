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
 * @class CentreonTopology
 */
class CentreonTopology
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonTopology constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param string $key
     * @param $value
     * @throws Exception
     * @return mixed
     */
    public function getTopology($key, $value)
    {
        $queryTopologyPage = 'SELECT * FROM topology WHERE topology_' . $key . ' = :keyTopo';
        $stmt = $this->db->prepare($queryTopologyPage);
        $stmt->bindParam(':keyTopo', $value);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * @param $topologyPage
     * @param string $topologyName
     * @param string $breadCrumbDelimiter
     *
     * @throws Exception
     * @return string
     */
    public function getBreadCrumbFromTopology($topologyPage, $topologyName, $breadCrumbDelimiter = ' > ')
    {
        $breadCrumb = $topologyName;
        $currentTopology = $this->getTopology('page', $topologyPage);

        while (! empty($currentTopology['topology_parent'])) {
            $parentTopology = $this->getTopology('page', $currentTopology['topology_parent']);
            $breadCrumb = $parentTopology['topology_name'] . $breadCrumbDelimiter . $breadCrumb;
            $currentTopology = $parentTopology;
        }

        return $breadCrumb;
    }
}
