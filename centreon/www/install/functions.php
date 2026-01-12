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

require_once __DIR__ . '/../../vendor/autoload.php';

use Core\Security\ProviderConfiguration\Domain\Local\Model\SecurityPolicy;

/**
 * Generate random password
 * 12 characters length with at least 1 uppercase, 1 lowercase, 1 number and 1 special character
 *
 * @return string
 */
function generatePassword(): string
{
    $ruleSets = [
        implode('', range('a', 'z')),
        implode('', range('A', 'Z')),
        implode('', range(0, 9)),
        SecurityPolicy::SPECIAL_CHARACTERS_LIST,
    ];
    $allRuleSets = implode('', $ruleSets);
    $passwordLength = 12;

    $password = '';
    foreach ($ruleSets as $ruleSet) {
        $password .= $ruleSet[random_int(0, strlen($ruleSet) - 1)];
    }

    for ($i = 0; $i < ($passwordLength - count($ruleSets)); $i++) {
        $password .= $allRuleSets[random_int(0, strlen($allRuleSets) - 1)];
    }

    return str_shuffle($password);
}
