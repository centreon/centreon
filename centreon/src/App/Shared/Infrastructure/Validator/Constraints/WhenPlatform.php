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

namespace App\Shared\Infrastructure\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;

/**
 * Applies the given constraints only when the running platform is (or is not) Cloud.
 *
 * The App equivalent of Core\Common\Infrastructure\Validator\Constraints\WhenPlatform, simplified
 * to a plain bool: App already treats "is this a Cloud platform" as a single boolean everywhere
 * (see e.g. CreateHostProcessor's $isCloudPlatform), there is no third platform type to model.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class WhenPlatform extends Composite
{
    /**
     * @param Constraint[] $constraints
     * @param string[]|null $groups
     */
    public function __construct(
        public bool $forCloud,
        public array $constraints,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    /**
     * @return string[]
     */
    public function getTargets(): array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
