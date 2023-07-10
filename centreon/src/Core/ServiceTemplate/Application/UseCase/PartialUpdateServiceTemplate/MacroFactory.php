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

namespace Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Assert\AssertionFailedException;
use Core\Macro\Domain\Model\Macro;

final class MacroFactory
{
    /**
     * Create macros object from the request data.
     * Use direct and inherited macros to retrieve value of macro with isPassword when not provided in dto.
     *
     * @param MacroDto $dto
     * @param int $serviceTemplateId
     * @param array<string,Macro> $directMacros
     * @param array<string,Macro> $inheritedMacros
     *
     * @throws AssertionFailedException
     *
     * @return Macro
     */
    public static function create(
        MacroDto $dto,
        int $serviceTemplateId,
        array $directMacros,
        array $inheritedMacros
    ): Macro {
        $macroName = mb_strtoupper($dto->name);
        $macroValue = $dto->value ?? '';
        $passwordHasNotChanged = (null === $dto->value) && $dto->isPassword;
        // Note: do not handle vault storage at the moment
        if ($passwordHasNotChanged) {
            $macroValue = match (true) {
                // retrieve actual password value
                isset($directMacros[$macroName]) => $directMacros[$macroName]->getValue(),
                isset($inheritedMacros[$macroName]) => $inheritedMacros[$macroName]->getValue(),
                default => $macroValue,
            };
        }

        $macro = new Macro(
            $serviceTemplateId,
            $dto->name,
            $macroValue,
        );
        $macro->setIsPassword($dto->isPassword);
        $macro->setDescription($dto->description ?? '');

        return $macro;
    }
}
