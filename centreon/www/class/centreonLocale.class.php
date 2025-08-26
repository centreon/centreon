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
 * @class CentreonLocale
 */
class CentreonLocale
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonLocale constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * GetLocaleList
     *
     * @throws PDOException
     * @return array
     */
    public function getLocaleList()
    {
        $res = $this->db->query(
            'SELECT locale_id, locale_short_name, locale_long_name, locale_img '
            . "FROM locale ORDER BY locale_short_name <> 'en', locale_short_name"
        );
        $list = [];
        while ($row = $res->fetchRow()) {
            $list[$row['locale_id']] = ['locale_short_name' => _($row['locale_short_name']), 'locale_long_name' => _($row['locale_long_name']), 'locale_img' => _($row['locale_img'])];
        }

        return $list;
    }
}
