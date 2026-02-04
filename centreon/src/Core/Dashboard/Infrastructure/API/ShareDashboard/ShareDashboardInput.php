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

namespace Core\Dashboard\Infrastructure\API\ShareDashboard;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShareDashboardInput
{
    /**
     * @param array<array{id: int, role: string}> $contacts
     * @param array<array{id: int, role: string}> $contactGroups
     */
    public function __construct(
        #[Assert\All([
            new Assert\Collection([
                'id' => [
                    new Assert\NotBlank(),
                    new Assert\Type('integer'),
                ],
                'role' => [
                    new Assert\NotBlank(),
                    new Assert\Choice([
                        'choices' => ['editor', 'viewer'],
                        'message' => 'Role provided for contact is not valid. Valid roles are: editor, viewer',
                    ]),
                ],
            ]),
        ])]
        public array $contacts = [],
        #[Assert\All([
            new Assert\Collection([
                'id' => [
                    new Assert\NotBlank(),
                    new Assert\Type('integer'),
                ],
                'role' => [
                    new Assert\NotBlank(),
                    new Assert\Choice([
                        'choices' => ['editor', 'viewer'],
                        'message' => 'Role provided for contact group is not valid. Valid roles are: editor, viewer',
                    ]),
                ],
            ]),
        ])]
        public array $contactGroups = [],
    ) {
    }
}
