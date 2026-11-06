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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\PatchServiceCommandFactory;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class PatchServiceCommandFactoryTest extends TestCase
{
    public function testItCarriesTheServiceAndTheActor(): void
    {
        $actor = TestActingUsers::restricted(7);

        $command = $this->create(new PatchServiceInput(), '{}', $actor);

        self::assertSame(12, $command->id->value);
        self::assertSame($actor, $command->actor);
    }

    public function testAnActivationLeftOutIsLeftUntouched(): void
    {
        $command = $this->create(new PatchServiceInput(), '{}');

        self::assertInstanceOf(NoValue::class, $command->activated);
    }

    #[DataProvider('activations')]
    public function testASentActivationIsCarried(bool $activated, string $json): void
    {
        $command = $this->create(new PatchServiceInput(activated: $activated), $json);

        self::assertSame($activated, $command->activated);
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function activations(): iterable
    {
        yield 'enable' => [true, '{"activated": true}'];

        yield 'disable' => [false, '{"activated": false}'];
    }

    private function create(PatchServiceInput $input, string $json, ?ActingUser $actor = null): PatchServiceCommand
    {
        return (new PatchServiceCommandFactory())->create(
            id: new ServiceId(12),
            actor: $actor ?? TestActingUsers::unrestricted(),
            input: $input,
            payload: RequestPayload::fromRequest(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json'])),
        );
    }
}
