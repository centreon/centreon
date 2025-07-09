<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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
 *
 * For more information : contact@centreon.com
 *
 */
declare(strict_types=1);

namespace Tests\Centreon\Infrastructure\HostConfiguration\Repository\Model;

use Centreon\Infrastructure\HostConfiguration\Repository\Model\HostGroupFactoryRdb;
use PHPUnit\Framework\TestCase;

/**
 * @package Tests\Centreon\Infrastructure\HostConfiguration\Repository\Model
 */
class HostGroupFactoryRdbTest extends TestCase
{
    /**
     * @var array<string, string|int> $rdbData
     */
    private $rdbData;

    protected function setUp(): void
    {
        $this->rdbData = [
            'hg_id' => 10,
            'hg_name' => 'hg name',
            'hg_alias' => 'hg alias',
            'geo_coords' => '2;4',
            'hg_comment' => 'comment',
            'hg_activate' => '1'
        ];
    }

    /**
     * Tests the of the good creation of the hostGroup entity.<br>
     * We test all properties.
     *
     * @throws \Assert\AssertionFailedException
     */
    public function testAllPropertiesOnCreate(): void
    {
        $hostGroup = HostGroupFactoryRdb::create($this->rdbData);
        $this->assertEquals($this->rdbData['hg_id'], $hostGroup->getId());
        $this->assertEquals($this->rdbData['hg_name'], $hostGroup->getName());
        $this->assertEquals($this->rdbData['hg_alias'], $hostGroup->getAlias());
        $this->assertEquals($this->rdbData['geo_coords'], $hostGroup->getGeoCoords());
        $this->assertEquals($this->rdbData['hg_comment'], $hostGroup->getComment());
        $this->assertEquals((bool) $this->rdbData['hg_activate'], $hostGroup->isActivated());
    }
}
