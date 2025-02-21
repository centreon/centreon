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

use Core\Common\Domain\PlatformEnum;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Exception\LogicException;

/**
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
    public function __construct(
        public string $platform,
        public array|Constraint|null $constraints = null,
        ?array $groups = null,
        $payload = null,
        array $options = []
    ) {
        $options['platform'] = $platform;
        $options['constraints'] = $constraints;

        if (isset($options['constraints']) && !\is_array($options['constraints'])) {
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
