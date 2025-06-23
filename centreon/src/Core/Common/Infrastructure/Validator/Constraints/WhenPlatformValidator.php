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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class WhenPlatformValidator extends ConstraintValidator
{
    public function __construct(private readonly bool $isCloudPlatform)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof WhenPlatform) {
            throw new UnexpectedTypeException($constraint, WhenPlatform::class);
        }

        $context = $this->context;

        if ($this->platformMatch($constraint->platform)) {
            $context->getValidator()->inContext($context)
                ->validate($value, $constraint->constraints);
        }
    }

    private function platformMatch(string $platform): bool
    {
        return ($this->isCloudPlatform === true && $platform === PlatformType::CLOUD)
            || ($this->isCloudPlatform === false && $platform === PlatformType::ON_PREM);
    }
}
