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

namespace CentreonClapi;

use CentreonDB;
use PDOException;

/**
 * Class
 *
 * @class CentreonUtils
 * @package CentreonClapi
 */
class CentreonUtils
{
    /** @var string */
    private static $clapiUserName;

    /** @var int */
    private static $clapiUserId;

    /**
     * Converts strings such as #S# #BS# #BR#
     *
     * @param string $pattern
     * @return string
     */
    public static function convertSpecialPattern($pattern)
    {
        $pattern = str_replace('#S#', '/', $pattern);
        $pattern = str_replace('#BS#', '\\', $pattern);

        return str_replace('#BR#', "\n", $pattern);
    }

    /**
     * @param string $imagename
     * @param CentreonDB|null $db
     *
     * @throws PDOException
     * @return int|null
     */
    public static function getImageId($imagename, $db = null)
    {
        if (is_null($db)) {
            $db = new CentreonDB('centreon');
        }
        $tab = preg_split("/\//", $imagename);
        $dirname = $tab[0] ?? null;
        $imagename = $tab[1] ?? null;

        if (! isset($imagename) || ! isset($dirname)) {
            return null;
        }

        $query = 'SELECT img.img_id FROM view_img_dir dir, view_img_dir_relation rel, view_img img '
            . 'WHERE dir.dir_id = rel.dir_dir_parent_id '
            . 'AND rel.img_img_id = img.img_id '
            . "AND img.img_path = '" . $imagename . "' "
            . "AND dir.dir_name = '" . $dirname . "' "
            . 'LIMIT 1';
        $res = $db->query($query);
        $img_id = null;
        $row = $res->fetchRow();
        if (isset($row['img_id']) && $row['img_id']) {
            $img_id = (int) $row['img_id'];
        }

        return $img_id;
    }

    /**
     * Convert Line Breaks \n or \r\n to <br/>
     *
     * @param string $str | string to convert
     * @return string
     */
    public static function convertLineBreak($str)
    {
        $str = str_replace("\r\n", '<br/>', $str);

        return str_replace("\n", '<br/>', $str);
    }

    /**
     * @param string $coords -90.0,180.0
     * @return bool
     */
    public static function validateGeoCoords($coords): bool
    {
        return (bool) (
            preg_match(
                '/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/',
                $coords
            )
        );
    }

    /**
     * @param string $userName
     *
     * @return void
     */
    public static function setUserName($userName): void
    {
        self::$clapiUserName = $userName;
    }

    /**
     * @return string
     */
    public static function getUserName()
    {
        return self::$clapiUserName;
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    public static function setUserId($userId): void
    {
        self::$clapiUserId = $userId;
    }

    /**
     * @return int
     */
    public static function getuserId()
    {
        return self::$clapiUserId;
    }
}
