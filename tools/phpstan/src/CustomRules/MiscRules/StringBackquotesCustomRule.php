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

namespace Tools\PhpStan\CustomRules\MiscRules;

use Tools\PhpStan\CustomRules\CentreonRuleErrorBuilder;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * This class implements custom rule for PHPStan to check if variable :db or :dbstg
 * are enclosed in backquotes.
 *
 * @implements Rule<Node\Scalar\String_>
 */
class StringBackquotesCustomRule implements Rule
{
    public const CENTREON_CONFIG_DATABASE = ':db';
    public const CENTREON_REALTIME_DATABASE = ':dbstg';
    private const REGEX = '/(' . self::CENTREON_REALTIME_DATABASE . '|' . self::CENTREON_CONFIG_DATABASE . ')\./';

    public function getNodeType(): string
    {
        return Node\Scalar\String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // This rule does not apply.
        if (
            ! preg_match_all(self::REGEX, $node->value, $matches)
            || empty($matches[1])
        ) {
            return [];
        }

        // Check rule.
        // $matches[0] = [':dbstg.',':db.']
        // $matches[1] = [':dbstg',':db']
        $errors = [];
        foreach ($matches[1] as $match) {
            $errors[] = CentreonRuleErrorBuilder::message($match . ' must be enclosed in backquotes.')->build();
        }

        return $errors;
    }
}
