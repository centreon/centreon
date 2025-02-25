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

namespace Core\Common\Infrastructure\Validator\Constraints;

use Core\Common\Domain\PlatformType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Strongly Inspired by Symfony\Component\Validator\Constraints\When
 *
 * Dedicated to handle by ourselves the context of the platform.
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(
    \Attribute::TARGET_CLASS
    | \Attribute::TARGET_PROPERTY
    | \Attribute::TARGET_METHOD
    | \Attribute::IS_REPEATABLE
)]
final class WhenPlatform extends Composite
{
    /**
     * @param string $platform
     * @param Constraint[]|Constraint|null $constraints
     * @param null|string[] $groups
     * @param mixed $payload
     * @param array<mixed> $options
     *
     * @throws LogicException
     * @throws ConstraintDefinitionException
     * @throws InvalidOptionsException
     * @throws MissingOptionsException
     */
    public function __construct(
        public string $platform,
        public array|Constraint|null $constraints = null,
        ?array $groups = null,
        $payload = null,
        array $options = []
    ) {
        if (! \in_array($platform, PlatformType::AVAILABLE_TYPES, true)) {
            throw new LogicException(\sprintf('The platform "%s" is not valid.', $platform));
        }

        $options['platform'] = $platform;
        $options['constraints'] = $constraints;

        if (isset($options['constraints']) && ! \is_array($options['constraints'])) {
            $options['constraints'] = [$options['constraints']];
        }

        if (null !== $groups) {
            $options['groups'] = $groups;
        }

        if (null !== $payload) {
            $options['payload'] = $payload;
        }

        parent::__construct($options);
    }

    public function getRequiredOptions(): array
    {
        return ['platform', 'constraints'];
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
