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

namespace Tools\PhpStan\CustomRules;

use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * This class defines a method to build a custom error message for PHPStan custom rules by
 * overloading its parent's method message().
 */
class CentreonRuleErrorBuilder
{
    /**
     * This method builds a custom error message for PHPStan custom rules by overloading its
     * parent's method message.
     *
     * @param string $message
     *
     * @return RuleErrorBuilder<RuleError>
     */
    public static function message(string $message): RuleErrorBuilder
    {
        return RuleErrorBuilder::message("[CENTREON-RULE]: {$message}");
    }
}
