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

namespace App\ResourceConfiguration\Infrastructure\Doctrine\GlobalMacro;

use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroComment;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroExpression;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroId;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroName;
use App\ResourceConfiguration\Infrastructure\Doctrine\GlobalMacro\DoctrineGlobalMacroRepository;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DoctrineGlobalMacroRepository
 *
 * @implements TransformerInterface<RawTypeAlias,GlobalMacro>
 */
final readonly class DoctrineGlobalMacroTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): GlobalMacro
    {
        return new GlobalMacro(
            id: new GlobalMacroId($from['gm_resource_id']),
            name: new GlobalMacroName($from['gm_resource_name']),
            expression: new GlobalMacroExpression($from['gm_resource_line']),
            comment: $from['gm_resource_comment'] !== null ? new GlobalMacroComment($from['gm_resource_comment']) : null,
            activated: $from['gm_resource_activate'] === '1',
            isPassword: $from['gm_is_password'] === 1,
        );
    }
}
