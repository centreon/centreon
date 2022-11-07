<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 *
 */

namespace CentreonModule\Infrastructure\Entity;

use CentreonModule\Infrastructure\Source\SourceDataInterface;

class Module implements SourceDataInterface
{
<<<<<<< HEAD
=======

>>>>>>> centreon/dev-21.10.x
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
<<<<<<< HEAD
     * @var array<int,string>
=======
     * @var array
>>>>>>> centreon/dev-21.10.x
     */
    private $images = [];

    /**
     * @var string
     */
    private $author;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $versionCurrent;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $stability = 'stable';

    /**
     * @var string
     */
    private $keywords;

    /**
<<<<<<< HEAD
     * @var array<string,string|bool>
=======
     * @var string
>>>>>>> centreon/dev-21.10.x
     */
    private $license;

    /**
     * @var string
     */
    protected $lastUpdate;

    /**
     * @var string
     */
    protected $releaseNote;

    /**
     * @var bool
     */
    private $isInstalled = false;

    /**
     * @var bool
     */
    private $isUpdated = false;

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getId(): string
    {
        return $this->id;
    }

<<<<<<< HEAD
    /**
     * @param string $id
     */
    public function setId(string $id): void
=======
    public function setId(string $id)
>>>>>>> centreon/dev-21.10.x
    {
        $this->id = $id;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getType(): string
    {
        return $this->type;
    }

<<<<<<< HEAD
    /**
     * @param string $type
     */
    public function setType(string $type): void
=======
    public function setType(string $type)
>>>>>>> centreon/dev-21.10.x
    {
        $this->type = $type;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getName(): string
    {
        return $this->name;
    }

<<<<<<< HEAD
    /**
     * @param string $name
     */
    public function setName(string $name): void
=======
    public function setName(string $name)
>>>>>>> centreon/dev-21.10.x
    {
        $this->name = $name;
    }

<<<<<<< HEAD
    /**
     * @return string|null
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getDescription(): ?string
    {
        return $this->description;
    }

<<<<<<< HEAD
    /**
     * @param string $description
     */
    public function setDescription(string $description): void
=======
    public function setDescription(string $description)
>>>>>>> centreon/dev-21.10.x
    {
        $this->description = $description;
    }

<<<<<<< HEAD
    /**
     * @return array<int,string>
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getImages(): array
    {
        return $this->images;
    }

<<<<<<< HEAD
    /**
     * @param string $image
     */
    public function addImage(string $image): void
=======
    public function addImage(string $image)
>>>>>>> centreon/dev-21.10.x
    {
        $this->images[] = $image;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getAuthor(): string
    {
        return $this->author;
    }

<<<<<<< HEAD
    /**
     * @param string $author
     */
    public function setAuthor(string $author): void
=======
    public function setAuthor(string $author)
>>>>>>> centreon/dev-21.10.x
    {
        $this->author = $author;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getVersion(): string
    {
        return $this->version;
    }

<<<<<<< HEAD
    /**
     * @param string $version
     */
    public function setVersion(string $version): void
=======
    public function setVersion(string $version)
>>>>>>> centreon/dev-21.10.x
    {
        $this->version = $version;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getVersionCurrent(): ?string
    {
        return $this->versionCurrent;
    }

<<<<<<< HEAD
    /**
     * @param string $versionCurrent
     */
    public function setVersionCurrent(string $versionCurrent): void
=======
    public function setVersionCurrent(string $versionCurrent)
>>>>>>> centreon/dev-21.10.x
    {
        $this->versionCurrent = $versionCurrent;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getPath(): string
    {
        return $this->path;
    }

<<<<<<< HEAD
    /**
     * @param string $path
     */
    public function setPath(string $path): void
=======
    public function setPath(string $path)
>>>>>>> centreon/dev-21.10.x
    {
        $this->path = $path;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getStability(): string
    {
        return $this->stability;
    }

<<<<<<< HEAD
    /**
     * @param string $stability
     */
    public function setStability(string $stability): void
=======
    public function setStability(string $stability)
>>>>>>> centreon/dev-21.10.x
    {
        $this->stability = $stability;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getKeywords(): string
    {
        return $this->keywords;
    }

<<<<<<< HEAD
    /**
     * @param string $keywords
     */
    public function setKeywords(string $keywords): void
=======
    public function setKeywords(string $keywords)
>>>>>>> centreon/dev-21.10.x
    {
        $this->keywords = $keywords;
    }

<<<<<<< HEAD
    /**
     * @return array<string,string|bool>|null
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getLicense(): ?array
    {
        return $this->license;
    }

<<<<<<< HEAD
    /**
     * @param array<mixed>|null $license
     */
    public function setLicense(array $license = null): void
=======
    public function setLicense(array $license = null)
>>>>>>> centreon/dev-21.10.x
    {
        $this->license = $license;
    }

<<<<<<< HEAD
    /**
     * @return string|null
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getLastUpdate(): ?string
    {
        return $this->lastUpdate;
    }

<<<<<<< HEAD
    /**
     * @param string $lastUpdate
     */
    public function setLastUpdate(string $lastUpdate): void
=======
    public function setLastUpdate(string $lastUpdate)
>>>>>>> centreon/dev-21.10.x
    {
        $this->lastUpdate = $lastUpdate;
    }

<<<<<<< HEAD
    /**
     * @return string|null
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getReleaseNote(): ?string
    {
        return $this->releaseNote;
    }

<<<<<<< HEAD
    /**
     * @param string $releaseNote
     */
    public function setReleaseNote(string $releaseNote): void
=======
    public function setReleaseNote(string $releaseNote)
>>>>>>> centreon/dev-21.10.x
    {
        $this->releaseNote = $releaseNote;
    }

<<<<<<< HEAD
    /**
     * @return bool
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function isInstalled(): bool
    {
        return $this->isInstalled;
    }

<<<<<<< HEAD
    /**
     * @param bool $value
     * @return bool
     */
    public function setInstalled(bool $value): void
=======
    public function setInstalled(bool $value)
>>>>>>> centreon/dev-21.10.x
    {
        $this->isInstalled = $value;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function isUpdated(): bool
    {
        return $this->isUpdated;
    }

<<<<<<< HEAD
    /**
     * @param bool $value
     * @return bool
     */
    public function setUpdated(bool $value): void
=======
    public function setUpdated(bool $value)
>>>>>>> centreon/dev-21.10.x
    {
        $this->isUpdated = $value;
    }
}
