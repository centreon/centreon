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

class ViewImg
{
    public const TABLE = 'view_img';

    /** @var int */
    private $imgId;

    /** @var string */
    private $imgName;

    /** @var string */
    private $imgPath;

    /** @var string */
    private $imgComment;

    public function setImgId(int $imgId): void
    {
        $this->imgId = $imgId;
    }

    public function getImgId(): int
    {
        return $this->imgId;
    }

    public function setImgName(string $imgName): void
    {
        $this->imgName = $imgName;
    }

    public function getImgName(): ?string
    {
        return $this->imgName;
    }

    public function setImgPath(string $imgPath): void
    {
        $this->imgPath = $imgPath;
    }

    public function getImgPath(): ?string
    {
        return $this->imgPath;
    }

    public function setImgComment(string $imgComment): void
    {
        $this->imgComment = $imgComment;
    }

    public function getImgComment(): ?string
    {
        return $this->imgComment;
    }
}
