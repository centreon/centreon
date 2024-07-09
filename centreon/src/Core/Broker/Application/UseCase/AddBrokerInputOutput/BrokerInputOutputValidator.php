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

namespace Core\Broker\Application\UseCase\AddBrokerInputOutput;

use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\Repository\ReadBrokerRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutputField;

class BrokerInputOutputValidator
{
    public function __construct(
        private readonly ReadBrokerRepositoryInterface $readBrokerRepository,
    ) {

    }

    public function brokerIsValidOrFail(int $brokerId): void {
        if (! ($this->readBrokerRepository->exists($brokerId))) {
            throw BrokerException::notFound($brokerId);
        }
    }

    /**
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $fields
     * @param array<string,mixed> $values
     *
     * @throws BrokerException
     */
    public function validateParameters(array $fields, array $values): void
    {
        foreach ($fields as $fieldName => $fieldInfo) {
            if (is_array($fieldInfo)) {
                if (($subField = current($fieldInfo)) && $subField->getType() === 'multiselect') {
                    // multiselect Field

                    $composedName = "{$fieldName}_{$subField->getName()}";

                    if (! array_key_exists($composedName, $values)) {
                        throw BrokerException::missingParameter($composedName);
                    }

                    if (! is_array($values[$composedName])) {
                        throw BrokerException::invalidParameterType($composedName, $values[$composedName]);
                    }
                    foreach ($values[$composedName] as $subValue) {
                        if (! is_string($subValue)) {
                            throw BrokerException::invalidParameterType($composedName, $subValue);
                        }
                        if (! in_array($subValue, $subField->getListValues(), true)) {
                            throw BrokerException::invalidParameter($composedName, $subValue);
                        }
                    }
                } else {
                    // grouped fields

                    if (! array_key_exists($fieldName, $values)) {
                        throw BrokerException::missingParameter($fieldName);
                    }

                    if (! is_array($values[$fieldName])) {
                        throw BrokerException::invalidParameterType(
                            "{$fieldName}",
                            $values[$fieldName]
                        );
                    }

                    foreach ($values[$fieldName] as $groupedValues) {
                        foreach ($fieldInfo as $groupedFieldName => $groupFieldInfo) {
                            if (! array_key_exists($groupedFieldName, $groupedValues)) {
                                throw BrokerException::missingParameter("{$fieldName}[].{$groupedFieldName}");
                            }
                            $this->validateFieldOrFail(
                                name: "{$fieldName}[].{$groupedFieldName}",
                                value: $groupedValues[$groupedFieldName],
                                field: $groupFieldInfo
                            );
                        }
                    }
                }
            } else {
                // simple field
                if (! array_key_exists($fieldName, $values)) {
                    throw BrokerException::missingParameter($fieldName);
                }
                $this->validateFieldOrFail($fieldName, $values[$fieldName], $fieldInfo);
            }
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param BrokerInputOutputField $field
     *
     * @throws BrokerException
     */
    private function validateFieldOrFail(string $name, mixed $value, BrokerInputOutputField $field): void
    {
        if ($field->isRequired() && (! isset($value) || '' === $value)) {
            throw BrokerException::missingParameter($name);
        }

        $isValid = match ($field->getType()) {
            'int' => $value === null || is_int($value),
            'text', 'password' => $value === null || is_string($value),
            'select', 'radio' => in_array($value, $field->getListValues(), true),
            default => false
        };

        if ($isValid === false) {
            throw BrokerException::invalidParameter($name, $value);
        }
    }
}
