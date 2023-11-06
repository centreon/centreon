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

declare(strict_types = 1);

namespace Core\Media\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class NewMedia
{
    private ?string $comment = null;

    /**
     * @param string $filename
     * @param string $directory
     * @param string $data
     *
     * @throws AssertionFailedException
     */
    public function __construct(private string $filename, private string $directory, readonly private string $data)
    {
        $this->filename = trim($this->filename);
        Assertion::notEmptyString($this->filename, 'Media::filename');
        $this->filename = str_replace(' ', '_', $this->filename);
        $this->directory = str_replace(' ', '', $this->directory);
        Assertion::notEmptyString($this->directory, 'Media::directory');
        Assertion::regex($this->directory, '/^[a-zA-Z0-9_-]+$/', 'Media::directory');
        Assertion::notEmptyString($this->data, 'Media::data');
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment !== null ? trim($comment) : null;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
