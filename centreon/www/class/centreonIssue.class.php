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
 * @class CentreonIssue
 */
class CentreonIssue
{
    /** @var CentreonDB */
    protected $dbb;

    /**
     * CentreonIssue constructor
     *
     * @param CentreonDB $dbb
     */
    public function __construct($dbb)
    {
        $this->dbb = $dbb;
    }

    /**
     * Get Children
     *
     * @param int $issueId
     *
     * @throws PDOException
     * @return array
     */
    public function getChildren($issueId)
    {
        $query = 'SELECT tb.issue_id, tb.host_id, tb.service_id, tb.start_time, tb.name, tb.description,
            tb.state, tb.output
        		  FROM (
					SELECT i.issue_id,
				   		   i.host_id,
				           i.service_id,
				           i.start_time,
				   		   h.name,
				   		   s.description,
				   		   s.state,
				   		   s.output
		    		FROM `hosts` h, `services` s, `issues` i, `issues_issues_parents` iip
					WHERE h.host_id = i.host_id
					AND s.service_id = i.service_id
					AND s.host_id = h.host_id
					AND i.issue_id = iip.child_id
					AND iip.parent_id = ' . $this->dbb->escape($issueId) . '

					UNION

					SELECT i2.issue_id,
				   		   i2.host_id,
				   		   i2.service_id,
				   		   i2.start_time,
				   		   h2.name,
				   		   NULL,
				   		   h2.state,
				   		   h2.output
					FROM `hosts` h2, `issues` i2, `issues_issues_parents` iip2
					WHERE h2.host_id = i2.host_id
					AND i2.service_id IS NULL
					AND i2.issue_id = iip2.child_id
					AND iip2.parent_id = ' . $this->dbb->escape($issueId) . '
        		  ) tb ';
        $res = $this->dbb->query($query);
        $childTab = [];
        while ($row = $res->fetchRow()) {
            foreach ($row as $key => $val) {
                $childTab[$row['issue_id']][$key] = $val;
            }
        }

        return $childTab;
    }

    /**
     * Check if issue is parent
     *
     * @param int $issueId
     *
     * @throws PDOException
     * @return bool
     */
    public function isParent($issueId)
    {
        $query = 'SELECT parent_id
            FROM issues_issues_parents
            WHERE parent_id = ' . $this->dbb->escape($issueId) . ' LIMIT 1';
        $res = $this->dbb->query($query);

        return (bool) ($res->rowCount());
    }
}
