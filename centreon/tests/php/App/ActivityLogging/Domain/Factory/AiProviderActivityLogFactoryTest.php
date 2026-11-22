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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\AiProviderActivityLogFactory;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use PHPUnit\Framework\TestCase;

final class AiProviderActivityLogFactoryTest extends TestCase
{
    private const TOKEN = 'sk-plain-provider-token-1234';

    public function testCreateLeavesTheApiTokenOut(): void
    {
        $firedAt = new \DateTimeImmutable();

        $activityLog = (new AiProviderActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: new AiProvider(
                id: new AiProviderId(42),
                name: new AiProviderName('OpenAI production'),
                type: AiProviderTypeEnum::OpenAi,
                endpointUrl: new EndpointUrl('https://api.openai.com'),
                apiToken: new StoredApiToken(self::TOKEN),
                defaultModel: new ModelName('gpt-5'),
                fastModel: null,
                description: null,
                isActivated: true,
            ),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('OpenAI production', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::AiProvider, $activityLog->target->type);
        self::assertSame(1, $activityLog->actor->id->value);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'name' => 'OpenAI production',
            'type' => 'openai',
            'endpoint_url' => 'https://api.openai.com',
            'default_model' => 'gpt-5',
            'is_activated' => '1',
        ], $activityLog->details);
        self::assertStringNotContainsString(self::TOKEN, json_encode($activityLog->details, \JSON_THROW_ON_ERROR));
    }
}
