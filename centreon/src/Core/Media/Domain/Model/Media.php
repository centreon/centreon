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
use Core\Common\Domain\Comparable;
use Core\Common\Domain\Identifiable;

class Media implements Comparable, Identifiable
{
    /**
     * @param int $id
     * @param string $filename
     * @param string $directory
     * @param string|null $comment
     * @param string|null $data
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        readonly private int $id,
        private string $filename,
        private string $directory,
        private ?string $comment,
        private ?string $data
    ) {
        Assertion::positiveInt($this->id, 'Media::id');
        $this->filename = trim($this->filename);
        Assertion::notEmptyString($this->filename, 'Media::filename');
        $this->filename = str_replace(' ', '_', $this->filename);
        $this->directory = str_replace(' ', '', $this->directory);
        Assertion::notEmptyString($this->directory, 'Media::directory');
        if ($this->comment !== null) {
            $this->comment = trim($this->comment);
        }
        Assertion::regex($this->directory, '/^[a-zA-Z0-9_-]+$/', 'Media::directory');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return string (ex: directory/filename)
     */
    public function getRelativePath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->filename;
    }

    public function hash(): ?string
    {
        return $this->data !== null ? md5($this->data) : null;
    }

    public function isEqual(object $object): bool
    {
        return $object instanceof self && $object->getEqualityHash() === $this->getEqualityHash();
    }

    public function getEqualityHash(): string
    {
        return md5($this->getRelativePath());
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

        return $this;
    }
}
