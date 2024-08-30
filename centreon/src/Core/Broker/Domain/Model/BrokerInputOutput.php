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

namespace Core\Broker\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

/**
 * @phpstan-type _BrokerInputOutputParameter int|string|string[]|array<array<int|string>>
 */
class BrokerInputOutput
{
    public const NAME_MAX_LENGTH = 255;

    /**
     * @param int $id
     * @param string $tag
     * @param Type $type
     * @param string $name
     * @param _BrokerInputOutputParameter[] $parameters
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly string $tag,
        private readonly Type $type,
        private string $name,
        private array $parameters,
    ) {
        Assertion::min($id, 0, 'BrokerInputOutput::id');
        Assertion::inArray($tag, ['input', 'output'], 'BrokerInputOutput::tag');

        $this->name = trim($name);
        Assertion::notEmptyString($this->name, 'BrokerInputOutput::name');
        Assertion::maxLength($this->name, self::NAME_MAX_LENGTH, 'BrokerInputOutput::name');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return _BrokerInputOutputParameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param _BrokerInputOutputParameter[] $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }
}
