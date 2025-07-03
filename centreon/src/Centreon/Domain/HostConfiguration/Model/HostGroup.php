<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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
declare(strict_types=1);

namespace Centreon\Domain\HostConfiguration\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Media\Model\Image;

/**
 * This class is designed to represent a host group.
 *
 * @package Centreon\Domain\HostConfiguration\Model
 */
class HostGroup
{
    public const MAX_NAME_LENGTH = 200;
    public const MAX_ALIAS_LENGTH = 200;
    public const MAX_GEO_COORDS_LENGTH = 32;
    public const MAX_COMMENTS_LENGTH = 65535;

    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $alias;

    /**
     * @var Image|null Define the image that should be associated with this host group.
     * This image will be displayed in the various places. The image will look best if it is 40x40 pixels in size.
     */
    private $icon;

    /**
     * @var string|null Geographical coordinates use by Centreon Map module to position element on map. <br>
     * Define "Latitude,Longitude", for example for Paris coordinates set "48.51,2.20"
     */
    private $geoCoords;

    /**
     * @var string|null Comments on this host group.
     */
    private $comment;

    /**
     * @var bool Indicates whether the host group is activated or not.
     */
    private $isActivated = true;

    /**
     * @param string $name Host Group name
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(string $name)
    {
        $this->setName($name);
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return HostGroup
     */
    public function setId(int $id): HostGroup
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return HostGroup
     * @throws \Assert\AssertionFailedException
     */
    public function setName(string $name): HostGroup
    {
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, 'HostGroup::name');
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @param string|null $alias
     * @return HostGroup
     * @throws \Assert\AssertionFailedException
     */
    public function setAlias(?string $alias): HostGroup
    {
        if ($alias !== null) {
            Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, 'HostGroup::alias');
        }
        $this->alias = $alias;
        return $this;
    }

    /**
     * @return Image|null
     */
    public function getIcon(): ?Image
    {
        return $this->icon;
    }

    /**
     * @param Image|null $icon
     * @return HostGroup
     */
    public function setIcon(?Image $icon): HostGroup
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGeoCoords(): ?string
    {
        return $this->geoCoords;
    }

    /**
     * @param string|null $geoCoords
     * @return HostGroup
     * @throws \Assert\AssertionFailedException
     */
    public function setGeoCoords(?string $geoCoords): HostGroup
    {
        if ($geoCoords !== null) {
            Assertion::maxLength($geoCoords, self::MAX_GEO_COORDS_LENGTH, 'HostGroup::geoCoords');
        }
        $this->geoCoords = $geoCoords;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $comment
     * @return HostGroup
     * @throws \Assert\AssertionFailedException
     */
    public function setComment(?string $comment): HostGroup
    {
        if ($comment !== null) {
            Assertion::maxLength($comment, self::MAX_COMMENTS_LENGTH, 'HostGroup::comment');
        }
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @param bool $isActivated
     * @return HostGroup
     */
    public function setActivated(bool $isActivated): HostGroup
    {
        $this->isActivated = $isActivated;
        return $this;
    }
}
