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

namespace Core\GraphTemplate\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class GraphTemplate
{
    public const NAME_MAX_LENGTH = 200;
    public const LABEL_MAX_LENGTH = 200;
    public const BASE_ALLOWED_VALUES = [1000, 1024];

    /**
     * @param int $id
     * @param string $name
     * @param string $verticalAxisLabel
     * @param int $width
     * @param int $height
     * @param int $base
     * @param null|float $gridLowerLimit
     * @param null|float $gridUpperLimit
     * @param bool $isUpperLimitSizedToMax
     * @param bool $isGraphScaled
     * @param bool $isDefaultCentreonTemplate
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private string $name,
        private string $verticalAxisLabel,
        private int $width,
        private int $height,
        private int $base = 1000,
        private ?float $gridLowerLimit = null,
        private ?float $gridUpperLimit = null,
        private bool $isUpperLimitSizedToMax = false,
        private bool $isGraphScaled = false,
        private bool $isDefaultCentreonTemplate = false,
    ) {
        Assertion::positiveInt($id, 'GraphTemplate::id');

        $this->name = trim($name);
        Assertion::notEmptyString($this->name, 'GraphTemplate::name');
        Assertion::maxLength($this->name, self::NAME_MAX_LENGTH, 'GraphTemplate::name');

        $this->verticalAxisLabel = trim($verticalAxisLabel);
        Assertion::notEmptyString($this->verticalAxisLabel, 'GraphTemplate::verticalAxisLabel');
        Assertion::maxLength($this->verticalAxisLabel, self::LABEL_MAX_LENGTH, 'GraphTemplate::verticalAxisLabel');

        Assertion::inArray($base, self::BASE_ALLOWED_VALUES, 'GraphTemplate::base');

        // if isUpperLimitSizedToMax is set to true, gridUpperLimit is silently set to null
        if ($this->isUpperLimitSizedToMax) {
            $this->gridUpperLimit = null;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVerticalAxisLabel(): string
    {
        return $this->verticalAxisLabel;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getBase(): int
    {
        return $this->base;
    }

    public function getGridLowerLimit(): ?float
    {
        return $this->gridLowerLimit;
    }

    public function getGridUpperLimit(): ?float
    {
        return $this->gridUpperLimit;
    }

    public function isUpperLimitSizedToMax(): bool
    {
        return $this->isUpperLimitSizedToMax;
    }

    public function isGraphScaled(): bool
    {
        return $this->isGraphScaled;
    }

    public function isDefaultCentreonTemplate(): bool
    {
        return $this->isDefaultCentreonTemplate;
    }
}
