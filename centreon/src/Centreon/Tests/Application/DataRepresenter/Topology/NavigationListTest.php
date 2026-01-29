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

namespace Centreon\Tests\Application\DataRepresenter\Topology;

use Centreon\Application\DataRepresenter\Topology\NavigationList;
use Centreon\Domain\Entity\Topology;
use PHPUnit\Framework\TestCase;

/**
 * @group Centreon
 * @group DataRepresenter
 */
class NavigationListTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $url = 'http://loca';
        $page = 'my-page';

        $entities = [
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(1);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('1');
                $entity->setTopologyParent(null);
                $entity->setTopologyGroup(null);
                $entity->setTopologyName('menu-A-lvl-1');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(2);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('2');
                $entity->setTopologyParent(null);
                $entity->setTopologyGroup(null);
                $entity->setTopologyName('menu-B-lvl-1');
                $entity->setIsReact('1');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(3);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('123');
                $entity->setTopologyParent(1);
                $entity->setTopologyGroup(null);
                $entity->setTopologyName('menu-C-lvl-2');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(4);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage(null);
                $entity->setTopologyParent(123);
                $entity->setTopologyGroup(3);
                $entity->setTopologyName('group-label');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(5);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('12345');
                $entity->setTopologyParent(123);
                $entity->setTopologyGroup(3);
                $entity->setTopologyName('menu-D-lvl-3');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(6);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('12346');
                $entity->setTopologyParent(123);
                $entity->setTopologyGroup(3);
                $entity->setTopologyName('menu-E-lvl-3');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(7);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('12347');
                $entity->setTopologyParent(123);
                $entity->setTopologyGroup(null);
                $entity->setTopologyName('menu-F-lvl-3');
                $entity->setIsReact('0');

                return $entity;
            })(),
            (function () use ($url) {
                $entity = new Topology();
                $entity->setTopologyId(87);
                $entity->setTopologyUrl($url);
                $entity->setTopologyPage('30101');
                $entity->setTopologyParent(301);
                $entity->setTopologyGroup(null);
                $entity->setTopologyName('orphan-lvl-3');
                $entity->setIsReact('0');

                return $entity;
            })(),
        ];

        $dataRepresenter = new NavigationList($entities, [
            'default' => [
                'color' => 'red',
            ],
            '1' => [
                'icon' => 'ico01',
                'color' => 'blue',
            ],
            '2' => [
                'icon' => 'ico02',
            ],
            '123' => [
                'icon' => 'ico03',
            ],
        ]);

        $this->assertStringEqualsFile(
            __DIR__ . '/../../../Resources/Fixture/Topology/navigation-list-01.json',
            json_encode($dataRepresenter->jsonSerialize())
        );
    }
}
