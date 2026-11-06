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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * A macro as submitted on a template write, addressed by id + parent: no id is a new macro, no
 * parent one of the template's own, a parent one it inherits.
 */
final readonly class HostTemplateMacroChange
{
    /**
     * @param ?int $id the id in the table $parent points to: the template's own macros, or the
     *                 inherited one's source
     * @param ?string $value null keeps the stored value of a password macro
     */
    public function __construct(
        public HostTemplateMacroName $name,
        #[Sensitive] public ?string $value,
        public bool $isPassword,
        public ?int $id = null,
        public ?HostMacroParentEnum $parent = null,
    ) {
        Assert::nullOrPositiveInteger($id);
        if ($value === null) {
            Assert::notNull($id, 'A null value keeps the stored value: a new macro (without id) must have a value.');
            Assert::true($isPassword, 'A null value keeps the stored value: only a password macro can do so.');
        } else {
            Assert::maxLength($value, HostTemplateMacro::MAX_VALUE_LENGTH);
        }
    }

    public function isNew(): bool
    {
        return $this->id === null;
    }

    public function keepsStoredValue(): bool
    {
        return $this->value === null;
    }
}
