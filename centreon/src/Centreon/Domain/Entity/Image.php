<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

use Symfony\Component\Serializer\Annotation as Serializer;
use ReflectionClass;

class Image
{
<<<<<<< HEAD
=======

>>>>>>> centreon/dev-21.10.x
    public const TABLE = 'view_img';
    public const MEDIA_DIR = 'img/media/';
    public const SERIALIZER_GROUP_LIST = 'image-list';

    /**
     * @Serializer\SerializedName("id")
     * @Serializer\Groups({Image::SERIALIZER_GROUP_LIST})
     * @var int
     */
    private $img_id;

    /**
     * @Serializer\SerializedName("name")
     * @Serializer\Groups({Image::SERIALIZER_GROUP_LIST})
     * @var string
     */
    private $img_name;

    /**
     * @var string
     */
    private $img_path;

    /**
     * @var string
     */
    private $img_comment;

    /**
     * @var ImageDir
     */
    private $imageDir;

    /**
     * Image constructor.
     */
    public function __construct()
    {
        $this->imageDir = new ImageDir();
    }

    /**
     * Load data in entity
     *
     * @param string $prop
     * @param string $val
     */
<<<<<<< HEAD
    public function __set($prop, $val): void
    {
        $ref = new ReflectionClass(ImageDir::class);
=======
    public function __set($prop, $val)
    {
        try {
            $ref = new ReflectionClass(ImageDir::class);
        } catch (\ReflectionException $e) {
        }

>>>>>>> centreon/dev-21.10.x
        $props = $ref->getProperties();
        $propArray = [];

        foreach ($props as $pro) {
            $propArray[] = $pro->getName();
        }

        if (in_array($prop, $propArray)) {
            $this->getImageDir()->{$prop} = $val;
        }
    }

    /**
     * Alias of getImgId
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->getImgId();
    }

    /**
     * @Serializer\Groups({Image::SERIALIZER_GROUP_LIST})
     * @Serializer\SerializedName("preview")
     * @return string
     */
    public function getPreview(): string
    {
        return static::MEDIA_DIR
            . $this->getImageDir()->getDirName()
            . '/' . $this->getImgPath();
    }

    /**
     * @return int|null
     */
    public function getImgId(): ?int
    {
        return $this->img_id;
    }

    /**
<<<<<<< HEAD
     * @param int $id
=======
     * @param int $img_id
>>>>>>> centreon/dev-21.10.x
     */
    public function setImgId(int $id): void
    {
        $this->img_id = $id;
    }

    /**
     * @return string|null
     */
    public function getImgName(): ?string
    {
        return $this->img_name;
    }

    /**
<<<<<<< HEAD
     * @param string $name
=======
     * @param string $img_name
>>>>>>> centreon/dev-21.10.x
     */
    public function setImgName(string $name = null): void
    {
        $this->img_name = $name;
    }

    /**
     * @return string|null
     */
    public function getImgPath(): ?string
    {
        return $this->img_path;
    }

    /**
<<<<<<< HEAD
     * @param string $path
=======
     * @param string $img_path
>>>>>>> centreon/dev-21.10.x
     */
    public function setImgPath(string $path = null): void
    {
        $this->img_path = $path;
    }

    /**
     * @return string|null
     */
    public function getImgComment(): ?string
    {
        return $this->img_comment;
    }

    /**
<<<<<<< HEAD
     * @param string $comment
=======
     * @param string $img_comment
>>>>>>> centreon/dev-21.10.x
     */
    public function setImgComment(string $comment = null): void
    {
        $this->img_comment = $comment;
    }

    /**
     * @return ImageDir
     */
    public function getImageDir(): ImageDir
    {
        return $this->imageDir;
    }

    /**
     * @param ImageDir $imageDir
     */
    public function setImageDir(ImageDir $imageDir = null): void
    {
        $this->imageDir = $imageDir;
    }

    /**
     * begin setters for subclass
     */

    /**
<<<<<<< HEAD
     * @param int $dirId
=======
     * @param int $dir_id
>>>>>>> centreon/dev-21.10.x
     */
    public function setDirId(int $dirId = null): void
    {
        $this->getImageDir()->setDirId($dirId);
    }

    /**
<<<<<<< HEAD
     * @param string $dirName
=======
     * @param string $dir_name
>>>>>>> centreon/dev-21.10.x
     */
    public function setDirName(string $dirName = null): void
    {
        $this->getImageDir()->setDirName($dirName);
    }

    /**
<<<<<<< HEAD
     * @param string $dirAlias
=======
     * @param string $dir_alias
>>>>>>> centreon/dev-21.10.x
     */
    public function setDirAlias(string $dirAlias = null): void
    {
        $this->getImageDir()->setDirAlias($dirAlias);
    }

    /**
<<<<<<< HEAD
     * @param string $dirComment
=======
     * @param string $dir_comment
>>>>>>> centreon/dev-21.10.x
     */
    public function setDirComment(string $dirComment = null): void
    {
        $this->getImageDir()->setDirComment($dirComment);
    }
}
