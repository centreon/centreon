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

namespace App\ResourceConfiguration\Infrastructure\Doctrine;

use App\ResourceConfiguration\Domain\Aggregate\GlobalMacro;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacroComment;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacroExpression;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacroId;
use App\ResourceConfiguration\Domain\Aggregate\GlobalMacroName;
use App\ResourceConfiguration\Infrastructure\Doctrine\DoctrineGlobalMacroRepository;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DoctrineGlobalMacroRepository
 *
 * @implements TransformerInterface<RawTypeAlias,GlobalMacro>
 */
final readonly class GlobalMacroTransformer implements TransformerInterface
{
    public function transform(object|array $from): GlobalMacro
    {
        return new GlobalMacro(
            id: new GlobalMacroId($from['resource_id']),
            name: new GlobalMacroName($from['resource_name']),
            expression: new GlobalMacroExpression($from['resource_line']),
            comment: $from['resource_comment'] !== null ? new GlobalMacroComment($from['resource_comment']) : null,
            activated: $from['resource_activate'] === '1',
            isPassword: $from['is_password'] === 1,
        );
    }
}
