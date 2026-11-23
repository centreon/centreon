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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateDataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\PatchHostTemplateCommandFactory;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class PatchHostTemplateCommandFactoryTest extends TestCase
{
    /**
     * @param ?class-string $nestedClass
     */
    #[DataProvider('everyPropertyOfTheInput')]
    public function testEveryPropertyOfTheInputReachesTheCommand(string $property, ?string $nestedClass, ?string $nestedProperty, ?string $sectionKey): void
    {
        $name = $nestedProperty ?? $property;
        $parameter = (new \ReflectionClass($nestedClass ?? PatchHostTemplateInput::class))->getConstructor()?->getParameters();
        $parameter = array_values(array_filter($parameter ?? [], static fn (\ReflectionParameter $candidate): bool => $candidate->getName() === $name))[0];
        [$value, $json] = self::sampleOf($parameter);

        if ($nestedClass === null) {
            $input = (new \ReflectionClass(PatchHostTemplateInput::class))->newInstanceArgs([$property => $value]);
            $body = [RequestPayload::keyOf($property) => $json];
            $untouched = $this->create(new PatchHostTemplateInput(), '{}');
        } else {
            $input = (new \ReflectionClass(PatchHostTemplateInput::class))->newInstanceArgs([$property => (new \ReflectionClass($nestedClass))->newInstanceArgs([$name => $value])]);
            $body = [$sectionKey => [RequestPayload::keyOf($name) => $json]];
            // The sub-object is sent in both, so that only the property tested can make the difference.
            $untouched = $this->create((new \ReflectionClass(PatchHostTemplateInput::class))->newInstanceArgs([$property => new $nestedClass()]), json_encode([$sectionKey => new \stdClass()], \JSON_THROW_ON_ERROR));
        }

        $command = $this->create($input, json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertNotEquals($untouched, $command, sprintf('"%s" is read from the input but never reaches the command.', $sectionKey === null ? RequestPayload::keyOf($name) : $sectionKey . '.' . RequestPayload::keyOf($name)));
    }

    /**
     * Every property of the input and of its sub-objects, found by reflection: a property added to the input
     * without being read by the factory makes this fail, with no test to write for it.
     *
     * @return iterable<string, array{string, ?class-string, ?string, ?string}>
     */
    public static function everyPropertyOfTheInput(): iterable
    {
        $subObjects = [
            'checkOptions' => [PatchHostTemplateCheckOptionsInput::class, 'check_options'],
            'extendedInformations' => [CreateHostTemplateExtendedInformationsInput::class, 'extended_informations'],
            'schedulingOptions' => [CreateHostTemplateSchedulingOptionsInput::class, 'scheduling_options'],
            'dataProcessing' => [CreateHostTemplateDataProcessingInput::class, 'data_processing'],
            'notifications' => [PatchHostTemplateNotificationsInput::class, 'notifications'],
        ];

        foreach ((new \ReflectionClass(PatchHostTemplateInput::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (! isset($subObjects[$parameter->getName()])) {
                yield $parameter->getName() => [$parameter->getName(), null, null, null];

                continue;
            }

            [$class, $sectionKey] = $subObjects[$parameter->getName()];
            foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $nested) {
                yield $sectionKey . '.' . $nested->getName() => [$parameter->getName(), $class, $nested->getName(), $sectionKey];
            }
        }
    }

    /**
     * A value for the parameter that is not its default, as the input holds it and as the body carries it.
     *
     * @return array{mixed, mixed}
     */
    private static function sampleOf(\ReflectionParameter $parameter): array
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

        if ($typeName === 'array') {
            return [['a'], ['a']];
        }

        if (is_a($typeName, \BackedEnum::class, true)) {
            $case = $typeName::cases()[0];

            return [$case, $case->value];
        }

        return match ($typeName) {
            'int' => [1, 1],
            'bool' => [true, true],
            'string' => match ($name) {
                'name' => ['a-name', 'a-name'],
                'enabled' => ['true', 'true'],
                default => ['x', 'x'],
            },
            default => throw new \LogicException(sprintf('No sample for the type "%s" of "%s".', $typeName, $name)),
        };
    }

    private function create(PatchHostTemplateInput $input, string $json): PatchHostTemplateCommand
    {
        return new PatchHostTemplateCommandFactory()->create(
            id: new HostTemplateId(1),
            actor: TestActingUsers::restricted(7),
            input: $input,
            payload: RequestPayload::fromRequest(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json'])),
        );
    }
}
