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

declare(strict_types=1);

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerFlowGroupEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerInputOutput;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerParameter;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\Shared\Domain\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrokerInputOutputTest extends TestCase
{
    public function testConstructWithMinimalArguments(): void
    {
        $flow = new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Output,
            groupId: 0,
            type: BrokerStreamTypeEnum::Ipv4,
            parameters: $this->parameters(),
        );

        self::assertSame(BrokerFlowGroupEnum::Output, $flow->group);
        self::assertSame(0, $flow->groupId);
        self::assertSame(BrokerStreamTypeEnum::Ipv4, $flow->type);
        self::assertCount(1, $flow->parameters);
    }

    /**
     * `config_group_id` indexes the flows within their group, so any position beyond the first
     * one is legitimate.
     */
    public function testConstructAllowsAGroupIdBeyondTheFirstFlow(): void
    {
        $flow = new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Input,
            groupId: 3,
            type: BrokerStreamTypeEnum::BbdoServer,
            parameters: $this->parameters(),
        );

        self::assertSame(3, $flow->groupId);
    }

    /**
     * The three modeled stream kinds relate to both `cb_tag_type_relation` tags, so each one is
     * valid as an input and as an output.
     */
    #[DataProvider('allowedGroupsProvider')]
    public function testConstructAllowsEveryGroupDeclaredByTheStreamType(
        BrokerStreamTypeEnum $type,
        BrokerFlowGroupEnum $group,
    ): void {
        $flow = new BrokerInputOutput($group, 0, $type, $this->parameters());

        self::assertSame($group, $flow->group);
    }

    /**
     * @return iterable<string, array{0: BrokerStreamTypeEnum, 1: BrokerFlowGroupEnum}>
     */
    public static function allowedGroupsProvider(): iterable
    {
        foreach (BrokerStreamTypeEnum::cases() as $type) {
            foreach ($type->allowedGroups() as $group) {
                yield $type->value . ' as ' . $group->value => [$type, $group];
            }
        }
    }

    public function testConstructRejectsANegativeGroupId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A broker flow group ID must be a non-negative index.');

        new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Output,
            groupId: -1,
            type: BrokerStreamTypeEnum::Ipv4,
            parameters: $this->parameters(),
        );
    }

    public function testConstructRejectsAFlowWithoutParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A broker flow must contain at least one parameter.');

        new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Output,
            groupId: 0,
            type: BrokerStreamTypeEnum::Ipv4,
            parameters: new Collection([], BrokerParameter::class),
        );
    }

    /**
     * @return Collection<BrokerParameter>
     */
    private function parameters(): Collection
    {
        return new Collection(
            [new BrokerParameter('name', 'central-module-master-output')],
            BrokerParameter::class,
        );
    }
}
