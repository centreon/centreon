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

namespace Tests\Core\ServiceTemplate\Domain;

use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;

it('return inheritance line in the expected order', function (): void {
    $serviceTemplateId = 27;
    $parents = [
        new ServiceTemplateInheritance(25, 27),
        new ServiceTemplateInheritance(12, 13),
        new ServiceTemplateInheritance(2, 45),
        new ServiceTemplateInheritance(13, 25),
        new ServiceTemplateInheritance(45, 10),
        new ServiceTemplateInheritance(1, 2),
        new ServiceTemplateInheritance(10, 12),
    ];
    $inheritanceLine = ServiceTemplateInheritance::createInheritanceLine($serviceTemplateId, $parents);
    expect($inheritanceLine)->toBe([25, 13, 12, 10, 45, 2, 1]);
});
