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

namespace Core\HostTemplate\Application\UseCase\AddHostTemplate;

use Assert\AssertionFailedException;
use Core\Macro\Domain\Model\Macro;

final class HostMacroFactory
{
    /**
     * Create macros object from the request data.
     * Use direct and inherited macros to retrieve value of macro with isPassword when not provided in dto.
     *
     * @param array{name:string,value:string|null,is_password:bool,description:string|null} $data
     * @param int $hostTemplateId
     * @param array<string,Macro> $inheritedMacros
     *
     * @throws \Throwable
     * @throws AssertionFailedException
     *
     * @return Macro
     */
    public static function create(
        array $data,
        int $hostTemplateId,
        array $inheritedMacros
    ): Macro {
        $macroName = mb_strtoupper($data['name']);
        $macroValue = $data['value'] ?? '';
        $passwordHasNotChanged = (null === $data['value']) && $data['is_password'];
        // Note: do not handle vault storage at the moment
        if ($passwordHasNotChanged) {
            $macroValue = match (true) {
                // retrieve actual password value
                isset($inheritedMacros[$macroName]) => $inheritedMacros[$macroName]->getValue(),
                default => $macroValue,
            };
        }

        $macro = new Macro(
            $hostTemplateId,
            $data['name'],
            $macroValue,
        );
        $macro->setIsPassword($data['is_password']);
        $macro->setDescription($data['description'] ?? '');

        return $macro;
    }
}
