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

namespace Centreon\Domain\Entity;

class CfgResource
{
    /** @var int */
    private $resourceId;

    /** @var string */
    private $resourceName;

    /** @var string */
    private $resourceLine;

    /** @var string */
    private $resourceComment;

    /** @var bool */
    private $resourceActivate;

    public function setResourceId(int $resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    public function setResourceName(string $resourceName): void
    {
        $this->resourceName = $resourceName;
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceLine(string $resourceLine): void
    {
        $this->resourceLine = $resourceLine;
    }

    public function getResourceLine(): string
    {
        return $this->resourceLine;
    }

    public function setResourceComment(string $resourceComment): void
    {
        $this->resourceComment = $resourceComment;
    }

    public function getResourceComment(): string
    {
        return $this->resourceComment;
    }

    public function setResourceActivate(bool $resourceActivate): void
    {
        $this->resourceActivate = (bool) $resourceActivate;
    }

    public function getResourceActivate(): bool
    {
        return (bool) $this->resourceActivate;
    }
}
