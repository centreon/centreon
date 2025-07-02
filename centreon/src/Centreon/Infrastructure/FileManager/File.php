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

namespace Centreon\Infrastructure\FileManager;

class File
{
    protected $name;

    protected $type;

    protected $extension;

    protected $tmp_name;

    protected $error;

    protected $size;

    public function __construct(array $data)
    {
        foreach ($data as $prop => $value) {
            $this->{$prop} = $value;
        }

        if ($this->name) {
            $this->extension = pathinfo($this->name, PATHINFO_EXTENSION);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getTmpName(): string
    {
        return $this->tmp_name;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getSize(): string
    {
        return $this->size;
    }
}
