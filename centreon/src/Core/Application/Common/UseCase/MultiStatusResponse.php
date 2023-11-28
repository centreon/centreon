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

namespace Core\Application\Common\UseCase;

final class MultiStatusResponse implements ResponseStatusInterface
{
    /**
     * @param array<mixed> $results
     */
    public function __construct(private readonly array $results)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return 'Multi-status';
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->results;
    }
}
