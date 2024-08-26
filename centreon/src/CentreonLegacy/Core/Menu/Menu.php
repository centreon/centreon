<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonLegacy\Core\Menu;

class Menu
{
    /** @var string|null The query filter for ACL */
    protected $acl = null;

    /** @var int|null The current topology page */
    protected $currentPage = null;

    /**
     * Constructor.
     *
     * @param \CentreonDB $db The configuration database connection
     * @param \CentreonUser $user The current user
     */
    public function __construct(protected $db, $user = null)
    {
        if (! is_null($user)) {
            $this->currentPage = $user->getCurrentPage();
            if (! $user->access->admin) {
                $this->acl = ' AND topology_page IN (' . $user->access->getTopologyString() . ') ';
            }
        }
    }

    /**
     * Get all menu (level 1 to 3).
     *
     * array(
     *   "p1" => array(
     *     "label" => "<level_one_label>",
     *     "url" => "<path_to_php_file>"
     *     "active" => "<true|false>"
     *     "color" => "<color_code>"
     *     "children" => array(
     *       "_101" => array(
     *         "label" => "<level_two_label>",
     *         "url" => "<path_to_php_file>",
     *         "active" => "<true|false>"
     *         "children" => array(
     *           "<group_name>" => array(
     *             "_10101" => array(
     *               "label" => "level_three_label",
     *               "url" => "<path_to_php_file>"
     *               "active" => "<true|false>"
     *             )
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     *
     * @return array The menu
     */
    public function getMenu()
    {
        $groups = $this->getGroups();

        $query = 'SELECT topology_name, topology_page, topology_url, topology_url_opt, '
            . 'topology_group, topology_order, topology_parent, is_react '
            . 'FROM topology '
            . 'WHERE topology_show = "1" '
            . 'AND topology_page IS NOT NULL';

        if (! is_null($this->acl)) {
            $query .= $this->acl;
        }

        $query .= ' ORDER BY topology_parent, topology_group, topology_order, topology_page';
        $stmt = $this->db->prepare($query);

        $stmt->execute();

        $currentLevelOne = null;
        $currentLevelTwo = null;
        $currentLevelThree = null;
        if (! is_null($this->currentPage)) {
            $currentLevelOne = substr($this->currentPage, 0, 1);
            $currentLevelTwo = substr($this->currentPage, 1, 2);
            $currentLevelThree = substr($this->currentPage, 2, 2);
        }

        $menu = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $active = false;
            if (preg_match('/^(\d)$/', (string) $row['topology_page'], $matches)) { // level 1
                if (! is_null($currentLevelOne) && $currentLevelOne == $row['topology_page']) {
                    $active = true;
                }
                $menu['p' . $row['topology_page']] = [
                    'label' => _($row['topology_name']),
                    'menu_id' => $row['topology_name'],
                    'url' => $row['topology_url'],
                    'active' => $active,
                    'color' => $this->getColor($row['topology_page']),
                    'children' => [],
                    'options' => $row['topology_url_opt'],
                    'is_react' => $row['is_react'],
                ];
            } elseif (preg_match('/^(\d)(\d\d)$/', (string) $row['topology_page'], $matches)) { // level 2
                if (! is_null($currentLevelTwo) && $currentLevelTwo == $row['topology_page']) {
                    $active = true;
                }
                /**
                 * Add prefix '_' to prevent json list to be reordered by
                 * the browser and to keep menu in order.
                 * This prefix will be remove by front-end.
                 */
                $menu['p' . $matches[1]]['children']['_' . $row['topology_page']] = [
                    'label' => _($row['topology_name']),
                    'url' => $row['topology_url'],
                    'active' => $active,
                    'children' => [],
                    'options' => $row['topology_url_opt'],
                    'is_react' => $row['is_react'],
                ];
            } elseif (preg_match('/^(\d)(\d\d)(\d\d)$/', (string) $row['topology_page'], $matches)) { // level 3
                if (! is_null($currentLevelThree) && $currentLevelThree == $row['topology_page']) {
                    $active = true;
                }
                $levelTwo = $matches[1] . $matches[2];
                $levelThree = [
                    'label' => _($row['topology_name']),
                    'url' => $row['topology_url'],
                    'active' => $active,
                    'options' => $row['topology_url_opt'],
                    'is_react' => $row['is_react'],
                ];
                if (! is_null($row['topology_group']) && isset($groups[$levelTwo][$row['topology_group']])) {
                    /**
                     * Add prefix '_' to prevent json list to be reordered by
                     * the browser and to keep menu in order.
                     * This prefix will be remove by front-end.
                     */
                    $menu['p' . $matches[1]]['children']['_' . $levelTwo]['children'][$groups[$levelTwo][$row['topology_group']]]['_' . $row['topology_page']] = $levelThree;
                } else {
                    /**
                     * Add prefix '_' to prevent json list to be reordered by
                     * the browser and to keep menu in order.
                     * This prefix will be remove by front-end.
                     */
                    $menu['p' . $matches[1]]['children']['_' . $levelTwo]['children']['Main Menu']['_' . $row['topology_page']] = $levelThree;
                }
            }
        }
        $stmt->closeCursor();

        return $menu;
    }

    /**
     * Get the list of groups.
     *
     * @return array The list of groups
     */
    public function getGroups()
    {
        $query = 'SELECT topology_name, topology_parent, topology_group FROM topology '
            . 'WHERE topology_show = "1" '
            . 'AND topology_page IS NULL '
            . 'ORDER BY topology_group, topology_order';
        $result = $this->db->query($query);

        $groups = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $groups[$row['topology_parent']][$row['topology_group']] = _($row['topology_name']);
        }

        $result->closeCursor();

        return $groups;
    }

    /**
     * Get menu color.
     *
     * @param int $pageId The page id
     *
     * @return string color
     */
    public function getColor($pageId)
    {
        $color = match ((int) $pageId) {
            1 => '#2B9E93',
            2 => '#85B446',
            3 => '#E4932C',
            5 => '#17387B',
            6 => '#319ED5',
            default => '#319ED5',
        };

        return $color;
    }
}
