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

namespace CentreonModule\Infrastructure\Service;

use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Source;
use Psr\Container\ContainerInterface;

class CentreonModuleService
{
    /** @var array<string,mixed> */
    protected $sources = [];

    /**
     * Construct.
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->initSources($services);
    }

    /**
     * @param string|null $search
     * @param bool|null $installed
     * @param bool|null $updated
     * @param array<mixed>|null $typeList
     *
     * @return array<string|int,Module[]>
     */
    public function getList(
        ?string $search = null,
        ?bool $installed = null,
        ?bool $updated = null,
        ?array $typeList = null,
    ): array {
        $result = [];

        if ($typeList !== null && $typeList) {
            $sources = [];

            foreach ($this->sources as $type => $source) {
                if (! in_array($type, $typeList) && ! $installed) {
                    continue;
                }

                $sources[$type] = $source;
            }
        } else {
            $sources = $this->sources;
        }

        foreach ($sources as $type => $source) {
            $list = $source->getList($search, $installed, $updated);

            $result[$type] = $this->sortList($list);
        }

        return $result;
    }

    /**
     * @param string $id
     * @param string $type
     *
     * @return Module|null
     */
    public function getDetail(string $id, string $type): ?Module
    {
        if (! array_key_exists($type, $this->sources)) {
            return null;
        }

        return $this->sources[$type]->getDetail($id);
    }

    /**
     * @param string $id
     * @param string $type
     *
     * @return Module|null
     */
    public function install(string $id, string $type): ?Module
    {
        if (! array_key_exists($type, $this->sources)) {
            return null;
        }

        return $this->sources[$type]->install($id);
    }

    /**
     * @param string $id
     * @param string $type
     *
     * @return Module|null
     */
    public function update(string $id, string $type): ?Module
    {
        if (! array_key_exists($type, $this->sources)) {
            return null;
        }

        return $this->sources[$type]->update($id);
    }

    public static function isOutdated(Module $module): bool
    {
        return $module->isInstalled() && ! $module->isUpdated();
    }

    /**
     * @return list<Module>
     */
    public function getOutdated(string $type): array
    {
        if (! array_key_exists($type, $this->sources)) {
            return [];
        }

        return $this->sources[$type]->getList(null, true, false);
    }

    /**
     * A failure does not stop the others: failures are reported once all were attempted.
     *
     * @throws \RuntimeException when some could not be updated
     *
     * @return list<Module> the updated modules or widgets
     */
    public function updateAll(string $type): array
    {
        $updated = [];
        $failures = [];
        $attempted = false;
        foreach ($this->getOutdated($type) as $outdated) {
            // Possibly upgraded as a dependency of one attempted before.
            if ($attempted) {
                $current = $this->getDetail($outdated->getId(), $type);
                if ($current === null || ! self::isOutdated($current)) {
                    continue;
                }
            }
            $attempted = true;

            try {
                $entity = $this->update($outdated->getId(), $type);
            } catch (\Throwable $exception) {
                $failures[] = \sprintf('%s: %s', $outdated->getId(), $exception->getMessage());

                continue;
            }

            if ($entity !== null) {
                $updated[] = $entity;
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException('Some updates failed. ' . implode(' ', $failures));
        }

        return $updated;
    }

    /**
     * @param string $id
     * @param string $type
     *
     * @return bool|null
     */
    public function remove(string $id, string $type): ?bool
    {
        if (! array_key_exists($type, $this->sources)) {
            return null;
        }

        $this->sources[$type]->remove($id);

        return true;
    }

    /**
     * Init list of sources.
     *
     * @param ContainerInterface $services
     */
    protected function initSources(ContainerInterface $services): void
    {
        $this->sources = [
            Source\ModuleSource::TYPE => new Source\ModuleSource($services),
            Source\WidgetSource::TYPE => new Source\WidgetSource($services),
        ];
    }

    /**
     * Sort list by:.
     *
     * - To update (then by name)
     * - To install (then by name)
     * - Installed (then by name)
     *
     * @param Module[] $list
     *
     * @return Module[]
     */
    protected function sortList(array $list): array
    {
        usort($list, function ($a, $b) {
            $aVal = $a->getName();
            $bVal = $b->getName();

            if ($aVal === $bVal) {
                return 0;
            }

            return ($aVal < $bVal) ? -1 : 1;
        });
        usort($list, function ($a, $b) {
            $sortByName = function ($a, $b) {
                $aVal = $a->isInstalled();
                $bVal = $b->isInstalled();

                if ($aVal === $bVal) {
                    return 0;
                }

                return ($aVal < $bVal) ? -1 : 1;
            };

            $aVal = $a->isInstalled() && ! $a->isUpdated();
            $bVal = $b->isInstalled() && ! $b->isUpdated();

            if ($aVal === $bVal) {
                return $sortByName($a, $b);
            }

            return ($aVal === true) ? -1 : 1;
        });

        return $list;
    }
}
