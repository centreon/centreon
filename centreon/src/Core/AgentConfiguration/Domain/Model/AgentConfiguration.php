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

namespace Core\AgentConfiguration\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

/**
 * @immutable
 */
class AgentConfiguration
{
    public const MAX_NAME_LENGTH = 255;

    private string $name = '';

    /**
     * @param int $id
     * @param string $name
     * @param Type $type
     * @param ConfigurationParametersInterface $configuration
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        private readonly Type $type,
        private readonly ConfigurationParametersInterface $configuration,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();
        $this->name = trim($name);
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, $shortName . '::name');
        Assertion::notEmptyString($this->name, $shortName . '::name');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * @return ConfigurationParametersInterface
     */
    public function getConfiguration(): ConfigurationParametersInterface
    {
        return $this->configuration;
    }
}
