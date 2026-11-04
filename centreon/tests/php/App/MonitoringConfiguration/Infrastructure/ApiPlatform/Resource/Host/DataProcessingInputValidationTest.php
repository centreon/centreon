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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingInput;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

/**
 * Cloud rejects the on-premise-only fields of the "Data Processing" sub-object; on-premise accepts them.
 */
final class DataProcessingInputValidationTest extends TestCase
{
    /**
     * @param array{acknowledgmentTimeout?: int, flapDetectionEnabled?: TriStateEnum, lowFlapThreshold?: int, highFlapThreshold?: int, eventHandlerArgs?: list<string>} $changes
     */
    #[DataProvider('onPremiseOnlyFieldProvider')]
    public function testCloudRejectsOnPremiseOnlyFields(array $changes, string $property, string $expected): void
    {
        self::assertSame([$expected], $this->violations(new DataProcessingInput(...$changes), $property, isCloudPlatform: true));
    }

    /**
     * @param array{acknowledgmentTimeout?: int, flapDetectionEnabled?: TriStateEnum, lowFlapThreshold?: int, highFlapThreshold?: int, eventHandlerArgs?: list<string>} $changes
     */
    #[DataProvider('onPremiseOnlyFieldProvider')]
    public function testOnPremiseAcceptsOnPremiseOnlyFields(array $changes, string $property): void
    {
        self::assertSame([], $this->violations(new DataProcessingInput(...$changes), $property, isCloudPlatform: false));
    }

    /**
     * @return iterable<string, array{array{acknowledgmentTimeout?: int, flapDetectionEnabled?: TriStateEnum, lowFlapThreshold?: int, highFlapThreshold?: int, eventHandlerArgs?: list<string>}, string, string}>
     */
    public static function onPremiseOnlyFieldProvider(): iterable
    {
        yield 'acknowledgment timeout' => [['acknowledgmentTimeout' => 5], 'acknowledgmentTimeout', 'acknowledgment_timeout is not available on a Cloud platform.'];

        yield 'flap detection' => [['flapDetectionEnabled' => TriStateEnum::True], 'flapDetectionEnabled', 'flap_detection_enabled is not available on a Cloud platform.'];

        yield 'low flap threshold' => [['lowFlapThreshold' => 10], 'lowFlapThreshold', 'low_flap_threshold is not available on a Cloud platform.'];

        yield 'high flap threshold' => [['highFlapThreshold' => 90], 'highFlapThreshold', 'high_flap_threshold is not available on a Cloud platform.'];

        yield 'event handler arguments' => [['eventHandlerArgs' => ['x']], 'eventHandlerArgs', 'event_handler_args is not available on a Cloud platform.'];
    }

    public function testCloudAcceptsEmptyEventHandlerArguments(): void
    {
        self::assertSame([], $this->violations(new DataProcessingInput(), 'eventHandlerArgs', isCloudPlatform: true));
    }

    /**
     * @return list<string>
     */
    private function violations(DataProcessingInput $input, string $property, bool $isCloudPlatform): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                WhenPlatformValidator::class => new WhenPlatformValidator($isCloudPlatform),
            ]))
            ->getValidator();

        $messages = [];
        foreach ($validator->validateProperty($input, $property) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
