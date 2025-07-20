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

namespace CentreonLegacy\Core\Menu;

use Centreon\Test\Mock\CentreonDB;

class MenuTest extends \PHPUnit\Framework\TestCase
{
    /** @var CentreonDB The database mock */
    private $db;

    public function setUp(): void
    {
        $this->db = new CentreonDB();
    }

    public function testGetGroups(): void
    {
        $this->db->addResultSet(
            'SELECT topology_name, topology_parent, topology_group FROM topology WHERE topology_show = "1" AND topology_page IS NULL ORDER BY topology_group, topology_order',
            [['topology_name' => 'By host', 'topology_parent' => 2, 'topology_group' => 201], ['topology_name' => 'By services', 'topology_parent' => 2, 'topology_group' => 202]]
        );
        $menu = new Menu($this->db);
        $this->assertEquals(
            $menu->getGroups(),
            [2 => [201 => 'By host', 202 => 'By services']]
        );
    }

    public function testGetColor(): void
    {
        $colorPageId3 = '#E4932C';
        $menu = new Menu($this->db);

        $this->assertEquals(
            $menu->getColor(3),
            $colorPageId3
        );
    }

    public function testGetMenuLevelOne(): void
    {
        $result = ['p2' => ['label' => 'By host', 'menu_id' => 'By host', 'url' => 'centreon/20101', 'active' => false, 'color' => '#85B446', 'children' => [], 'options' => '&o=c', 'is_react' => 0]];

        $this->db->addResultSet(
            'SELECT topology_name, topology_parent, topology_group FROM topology WHERE topology_show = "1" AND topology_page IS NULL ORDER BY topology_group, topology_order',
            [['topology_name' => 'By host', 'topology_parent' => '', 'topology_group' => 201]]
        );

        $this->db->addResultSet(
            'SELECT topology_name, topology_page, topology_url, topology_url_opt, topology_group, topology_order, topology_parent, is_react FROM topology WHERE topology_show = "1" AND topology_page IS NOT NULL ORDER BY topology_parent, topology_group, topology_order, topology_page',
            [['topology_name' => 'By host', 'topology_page' => 2, 'topology_url' => 'centreon/20101', 'topology_url_opt' => '&o=c', 'topology_parent' => '', 'topology_order' => 1, 'topology_group' => 201, 'is_react' => 0]]
        );

        $menu = new Menu($this->db);
        $this->assertEquals(
            $menu->getMenu(),
            $result
        );
    }

    public function testGetMenuLevelTwo(): void
    {
        $result = ['p2' => ['children' => ['_201' => ['label' => 'By host', 'url' => 'centreon/20101', 'active' => false, 'children' => [], 'options' => '&o=c', 'is_react' => 0]]]];

        $this->db->addResultSet(
            'SELECT topology_name, topology_parent, topology_group FROM topology WHERE topology_show = "1" AND topology_page IS NULL ORDER BY topology_group, topology_order',
            [['topology_name' => 'By host', 'topology_parent' => 2, 'topology_group' => 201]]
        );

        $this->db->addResultSet(
            'SELECT topology_name, topology_page, topology_url, topology_url_opt, topology_group, topology_order, topology_parent, is_react FROM topology WHERE topology_show = "1" AND topology_page IS NOT NULL ORDER BY topology_parent, topology_group, topology_order, topology_page',
            [['topology_name' => 'By host', 'topology_page' => 201, 'topology_url' => 'centreon/20101', 'topology_url_opt' => '&o=c', 'topology_parent' => 2, 'topology_order' => 1, 'topology_group' => 201, 'is_react' => 0]]
        );

        $menu = new Menu($this->db);
        $this->assertEquals(
            $menu->getMenu(),
            $result
        );
    }

    public function testGetMenuLevelThree(): void
    {
        $result = ['p2' => ['children' => ['_201' => ['children' => ['Main Menu' => ['_20101' => ['label' => 'By host', 'url' => 'centreon/20101', 'active' => false, 'options' => '&o=c', 'is_react' => 0]]]]]]];

        $this->db->addResultSet(
            'SELECT topology_name, topology_parent, topology_group FROM topology WHERE topology_show = "1" AND topology_page IS NULL ORDER BY topology_group, topology_order',
            [['topology_name' => 'By host', 'topology_parent' => 2, 'topology_group' => 201]]
        );

        $this->db->addResultSet(
            'SELECT topology_name, topology_page, topology_url, topology_url_opt, topology_group, topology_order, topology_parent, is_react FROM topology WHERE topology_show = "1" AND topology_page IS NOT NULL ORDER BY topology_parent, topology_group, topology_order, topology_page',
            [['topology_name' => 'By host', 'topology_page' => 20101, 'topology_url' => 'centreon/20101', 'topology_url_opt' => '&o=c', 'topology_parent' => 201, 'topology_order' => 1, 'topology_group' => 201, 'is_react' => 0]]
        );

        $menu = new Menu($this->db);
        $this->assertEquals(
            $menu->getMenu(),
            $result
        );
    }
}
