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

namespace Tests\Core\AgentConfiguration\Application\UseCase\FindPollers;

use Core\AgentConfiguration\Application\UseCase\FindPollers\FindPollersPresenterInterface;
use Core\AgentConfiguration\Application\UseCase\FindPollers\FindPollersResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;

class FindPollersPresenterStub implements FindPollersPresenterInterface
{
    public FindPollersResponse|ResponseStatusInterface $data;

    public function presentResponse(FindPollersResponse|ResponseStatusInterface $data): void
    {
        $this->data = $data;
    }
}
