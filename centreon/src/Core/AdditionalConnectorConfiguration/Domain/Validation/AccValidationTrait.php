<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\AdditionalConnectorConfiguration\Domain\Validation;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;

/**
 * This trait exists only here for DRY reasons.
 *
 * It gathers all the guard methods of common fields from {@see Acc} and {@see NewAcc} entities.
 */
trait AccValidationTrait
{
    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    private function ensureValidName(string $name): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, Acc::MAX_NAME_LENGTH, $shortName . '::name');
        Assertion::notEmptyString($name, $shortName . '::name');
    }

    /**
     * @param string $description
     *
     * @throws AssertionFailedException
     */
    private function ensureValidDescription(string $description): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($description, Acc::MAX_DESCRIPTION_LENGTH, $shortName . '::description');
    }

    /**
     * @param int $value
     * @param string $propertyName
     *
     * @throws AssertionFailedException
     */
    private function ensurePositiveInt(int $value, string $propertyName): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::positiveInt($value, $shortName . '::' . $propertyName);
    }

    /**
     * @param ?int $value
     * @param string $propertyName
     *
     * @throws AssertionFailedException
     */
    private function ensureNullablePositiveInt(?int $value, string $propertyName): void
    {
        if (null !== $value) {
            $this->ensurePositiveInt($value, $propertyName);
        }
    }
}
