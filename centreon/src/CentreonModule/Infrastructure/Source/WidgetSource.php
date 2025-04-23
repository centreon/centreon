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

namespace CentreonModule\Infrastructure\Source;

use CentreonLegacy\ServiceProvider as ServiceProviderLegacy;
use CentreonModule\Domain\Repository\WidgetModelsRepository;
use CentreonModule\Infrastructure\Entity\Module;
use Psr\Container\ContainerInterface;

class WidgetSource extends SourceAbstract
{
    public const TYPE = 'widget';
    public const PATH = 'www/widgets/';
    public const CONFIG_FILE = 'configs.xml';

    /** @var string[] */
    private $info;

    /**
     * Construct.
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->installer = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_WIDGET_INSTALLER);
        $this->upgrader = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_WIDGET_UPGRADER);
        $this->remover = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_WIDGET_REMOVER);

        parent::__construct($services);
    }

    public function initInfo(): void
    {
        $this->info = $this->db
            ->getRepository(WidgetModelsRepository::class)
            ->getAllWidgetsVersion();
    }

    /**
     * @param string|null $search
     * @param bool|null $installed
     * @param bool|null $updated
     *
     * @return array<int,\CentreonModule\Infrastructure\Entity\Module>
     */
    public function getList(?string $search = null, ?bool $installed = null, ?bool $updated = null): array
    {
        $files = $this->finder
            ->files()
            ->name(static::CONFIG_FILE)
            ->depth('== 1')
            ->sortByName()
            ->in($this->getPath());

        $result = [];

        foreach ($files as $file) {
            $entity = $this->createEntityFromConfig($file->getPathName());

            if (! $this->isEligible($entity, $search, $installed, $updated)) {
                continue;
            }

            $result[] = $entity;
        }

        return $result;
    }

    /**
     * @param string $id
     *
     * @return Module|null
     */
    public function getDetail(string $id): ?Module
    {
        $result = null;
        $path = $this->getPath() . $id;

        if (file_exists($path) === false) {
            return $result;
        }

        $files = $this->finder
            ->files()
            ->name(static::CONFIG_FILE)
            ->depth('== 0')
            ->sortByName()
            ->in($path);

        foreach ($files as $file) {
            $result = $this->createEntityFromConfig($file->getPathName());
        }

        return $result;
    }

    /**
     * @param string $configFile
     *
     * @return Module
     */
    public function createEntityFromConfig(string $configFile): Module
    {
        // force linux path format
        $configFile = str_replace(DIRECTORY_SEPARATOR, '/', $configFile);

        $xml = simplexml_load_file($configFile);

        $entity = new Module;
        $entity->setId(basename(dirname($configFile)));
        $entity->setPath(dirname($configFile));
        $entity->setType(static::TYPE);
        $entity->setName($xml->title->__toString());
        $entity->setDescription($xml->description->__toString());
        $entity->setAuthor($xml->author->__toString());
        $entity->setVersion($xml->version ? $xml->version->__toString() : null);
        $entity->setInternal($xml->version ? false : true);
        $entity->setKeywords($xml->keywords->__toString());

        if ($xml->stability) {
            $entity->setStability($xml->stability->__toString());
        }

        if ($xml->last_update) {
            $entity->setLastUpdate($xml->last_update->__toString());
        }

        if ($xml->release_note) {
            $entity->setReleaseNote($xml->release_note->__toString());
        }

        if ($xml->screenshot) {
            foreach ($xml->screenshot as $image) {
                if (! empty($image->__toString())) {
                    $entity->addImage($image->__toString());
                }
            }
            unset($image);
        }

        if ($xml->screenshots) {
            foreach ($xml->screenshots as $image) {
                if (! empty($image->screenshot['src']->__toString())) {
                    $entity->addImage($image->screenshot['src']->__toString());
                }
            }
            unset($image);
        }

        // load information about installed modules/widgets
        if ($this->info === null) {
            $this->initInfo();
        }

        if (array_key_exists($entity->getId(), $this->info)) {
            $entity->setVersionCurrent($this->info[$entity->getId()]);
            $entity->setInstalled(true);

            $isUpdated
                = $entity->isInternal() || $this->isUpdated($this->info[$entity->getId()], $entity->getVersion());
            $entity->setUpdated($isUpdated);
        }

        return $entity;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return string
     */
    protected function getPath(): string
    {
        return $this->path . static::PATH;
    }
}
