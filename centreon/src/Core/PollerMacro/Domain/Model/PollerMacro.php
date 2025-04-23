<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\PollerMacro\Domain\Model;

class PollerMacro
{
    public function __construct(
        private readonly int $id,
        private string $name,
        private string $value,
        private ?string $comment,
        private bool $isActive,
        private bool $isPassword,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isPassword(): bool
    {
        return $this->isPassword;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
