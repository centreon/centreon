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

use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * A custom macro a host template owns. The value is held as stored: the literal value, or the
 * `secret::` reference of a vaulted password.
 */
final readonly class HostTemplateMacro
{
    public const MAX_VALUE_LENGTH = 4096;

    /**
     * @param bool $isPassword when true the value is a secret, eligible for the vault and never
     *                         echoed back in a response
     * @param ?HostMacroId $id null until persisted
     */
    public function __construct(
        public HostMacroName $name,
        // The attribute is static, so every value is masked in logs, non-secret ones included.
        #[Sensitive] public string $value,
        public bool $isPassword,
        public ?HostMacroId $id = null,
    ) {
        Assert::maxLength($value, self::MAX_VALUE_LENGTH);
    }

    /**
     * A new row of a copy: the id is dropped, the value replaced by $value when one is given (a
     * re-minted secret).
     */
    public function copyWithoutIdentity(?string $value = null): self
    {
        return new self($this->name, $value ?? $this->value, $this->isPassword);
    }

    /**
     * The macro as a host, or a template inheriting from this one, reads it: direct when $parent is
     * null.
     */
    public function toHostMacro(?HostMacroParentEnum $parent = null): HostMacro
    {
        return new HostMacro(
            new HostMacroName($this->name->value),
            $this->value,
            $this->isPassword,
            $this->id instanceof HostMacroId ? new HostMacroId($this->id->value) : null,
            $parent,
        );
    }
}
