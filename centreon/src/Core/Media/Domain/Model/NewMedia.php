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
    public function __construct(private string $filename, private string $directory, private string $data)
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $this->filename = trim($this->filename);
        Assertion::notEmptyString($this->filename, "{$className}::filename");
        $this->filename = str_replace(' ', '_', $this->filename);
        $this->directory = str_replace(' ', '', $this->directory);
        Assertion::notEmptyString($this->directory, "{$className}::directory");
        Assertion::regex($this->directory, '/^[a-zA-Z0-9_-]+$/', "{$className}::directory");
        $this->data = trim($this->data);
        Assertion::notEmptyString($this->data, "{$className}::data");
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

    /**
     * @param Media $media
     *
     * @throws AssertionFailedException
     *
     * @return NewMedia
     */
    public static function createFromMedia(Media $media): self
    {
        $newMedia = new self(
            $media->getFilename(),
            $media->getDirectory(),
            $media->getData() ?? 'fake_data',
        );
        $newMedia->setComment($media->getComment());

        return $newMedia;
    }
}
