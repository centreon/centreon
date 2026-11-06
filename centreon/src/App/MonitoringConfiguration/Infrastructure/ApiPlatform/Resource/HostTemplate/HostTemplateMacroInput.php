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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroName;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\VaultInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class HostTemplateMacroInput
{
    public function __construct(
        // The short name: the stored `$_HOST<NAME>$` must fit its column.
        #[Assert\Sequentially([
            new Assert\NotBlank(normalizer: 'trim'),
            new Assert\Length(max: HostTemplateMacroName::MAX_STORAGE_LENGTH - 7, normalizer: 'trim'),
        ])]
        public string $name,

        // Null keeps the stored value of a password macro. No password is ever echoed back, so a
        // client has no legitimate vault reference to send.
        #[Sensitive]
        #[Assert\Length(max: HostTemplateMacro::MAX_VALUE_LENGTH)]
        #[Assert\Regex(
            pattern: '/^' . VaultInterface::VAULT_PATH_PREFIX . '/',
            match: false,
            message: 'A macro value cannot be a vault reference.',
        )]
        public ?string $value = null,

        public bool $isPassword = false,

        #[Assert\Positive]
        public ?int $id = null,

        #[Assert\Choice(callback: [self::class, 'parents'])]
        public ?string $parent = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function parents(): array
    {
        return array_map(static fn (HostMacroParentEnum $parent): string => $parent->value, HostMacroParentEnum::cases());
    }

    #[Assert\Callback]
    public function validateValueIsProvided(ExecutionContextInterface $context): void
    {
        if ($this->value === null && ($this->id === null || ! $this->isPassword)) {
            $context->buildViolation('A value is required, except to keep the stored value of an existing password macro.')
                ->atPath('value')
                ->addViolation();
        }
    }
}
