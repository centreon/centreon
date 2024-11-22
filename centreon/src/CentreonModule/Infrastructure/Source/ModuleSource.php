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

declare(strict_types=1);

namespace CentreonModule\Infrastructure\Source;

use CentreonLegacy\ServiceProvider as ServiceProviderLegacy;
use CentreonModule\Domain\Repository\ModulesInformationsRepository;
use CentreonModule\Infrastructure\Entity\Module;
use Psr\Container\ContainerInterface;

class ModuleSource extends SourceAbstract
{
    public const TYPE = 'module';
    public const PATH = 'www/modules/';
    public const PATH_WEB = 'modules/';
    public const CONFIG_FILE = 'conf.php';

    /** @var array<string,mixed> */
    protected $info;

    /**
     * Construct.
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->installer = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_MODULE_INSTALLER);
        $this->upgrader = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_MODULE_UPGRADER);
        $this->remover = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_MODULE_REMOVER);
        $this->license = $services->get(ServiceProviderLegacy::CENTREON_LEGACY_MODULE_LICENSE);

        parent::__construct($services);
    }

    public function initInfo(): void
    {
        $this->info = $this->db
            ->getRepository(ModulesInformationsRepository::class)
            ->getAllModuleVsVersion();
    }

    /**
     * {@inheritDoc}
     *
     * Install module
     *
     * @throws ModuleException
     */
    public function install(string $id): ?Module
    {
        /**
         * Do not try to install the module if the package is not installed (deb or rpm).
         */
        if (($module = $this->getDetail($id)) === null) {
            throw ModuleException::cannotFindModuleDetails($id);
        }
        /**
         * Check if the module has dependencies
         * if it does, then check if those dependencies are installed or need updates
         * if not installed -> install the dependency
         * if not up to date -> update the dependency.
         */
        $this->installOrUpdateDependencies($id);

        /**
         * Do not execute the install process for the module if it is already installed.
         */
        return $module->isInstalled() === false ? parent::install($id) : $module;
  }

    /**
     * {@inheritDoc}
     *
     * Remove module
     *
     * @throws ModuleException
     */
    public function remove(string $id): void
    {
        $this->validateRemovalRequirementsOrFail($id);

        $recordId = $this->db
            ->getRepository(ModulesInformationsRepository::class)
            ->findIdByName($id);

        ($this->remover)($id, $recordId)->remove();
    }

    /**
     * {@inheritDoc}
     *
     * Update module
     *
     * @throws ModuleException
     */
    public function update(string $id): ?Module
    {
        $this->installOrUpdateDependencies($id);

        $recordId = $this->db
            ->getRepository(ModulesInformationsRepository::class)
            ->findIdByName($id);

        ($this->upgrader)($id, $recordId)->upgrade();

        $this->initInfo();

        return $this->getDetail($id);
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
        $files = ($this->finder::create())
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

        $files = ($this->finder::create())
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
        $module_conf = [];

        $module_conf = $this->getModuleConf($configFile);

        $info = current($module_conf);

        $entity = new Module();
        $entity->setId(basename(dirname($configFile)));
        $entity->setPath(dirname($configFile));
        $entity->setType(static::TYPE);
        $entity->setName($info['rname']);
        $entity->setAuthor($info['author']);
        $entity->setVersion($info['mod_release']);
        $entity->setDescription($info['infos']);
        $entity->setKeywords($entity->getId());
        $entity->setLicense($this->getLicenseInformationForModule($entity->getId(), $info));

        if (array_key_exists('stability', $info) && $info['stability']) {
            $entity->setStability($info['stability']);
        }

        if (array_key_exists('last_update', $info) && $info['last_update']) {
            $entity->setLastUpdate($info['last_update']);
        }

        if (array_key_exists('release_note', $info) && $info['release_note']) {
            $entity->setReleaseNote($info['release_note']);
        }

        if (array_key_exists('images', $info) && $info['images']) {
            if (is_string($info['images'])) {
                $info['images'] = [$info['images']];
            }

            foreach ($info['images'] as $image) {
                $entity->addImage(static::PATH_WEB . $entity->getId() . '/'. $image);
            }
        }

        if (array_key_exists('dependencies', $info) && is_array($info['dependencies'])) {
            $entity->setDependencies($info['dependencies']);
        }

        // load information about installed modules/widgets
        if ($this->info === null) {
            $this->initInfo();
        }

        if (array_key_exists($entity->getId(), $this->info)) {
            $entity->setVersionCurrent($this->info[$entity->getId()]);
            $entity->setInstalled(true);

            $isUpdated = $this->isUpdated($this->info[$entity->getId()], $entity->getVersion());
            $entity->setUpdated($isUpdated);
        }

        return $entity;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $configFile
     *
     * @return array<mixed>
     */
    protected function getModuleConf(string $configFile): array
    {
        $module_conf = [];

        require $configFile;

        return $module_conf;
    }

    /**
     * Process license check and return license information.
     *
     * @param string $slug the module id (slug)
     * @param array<string,mixed> $information the information of the module from conf.php
     *
     * @return array<string,mixed> the license information
     */
    protected function getLicenseInformationForModule(string $slug, array $information): array
    {
        if (empty($information['require_license'])) {
            return ['required' => false];
        }

        // if module requires a license, use License Manager to get information
        $dependencyInjector = \Centreon\LegacyContainer::getInstance();
        $license = $dependencyInjector['lm.license'];
        $license->setProductForModule($slug);
        $licenseInFileData = $license->getData();

        return [
            'required' => true,
            'expiration_date' => $this->license->getLicenseExpiration($slug),
            'host_usage' => $this->getHostUsage(),
            'is_valid' => $license->validate(),
            'host_limit' => $licenseInFileData['licensing']['hosts'] ?? -1,
        ];
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

    /**
     * Return the number actively used.
     *
     * @return int|null
     */
    private function getHostUsage(): ?int
    {
        $database = new \CentreonDB();
        $request = <<<'SQL'
                SELECT COUNT(*) AS `num` FROM host WHERE host_register = "1"
            SQL;

        $statement = $database->query($request);
        if ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            return (int) $record['num'];
        }

        return null;
    }

    /**
     * Install or update module dependencies when needed.
     *
     * @param string $moduleId
     *
     * @throws ModuleException
     */
    private function installOrUpdateDependencies(string $moduleId): void
    {
        $sortedDependencies = $this->getSortedDependencies($moduleId);
        foreach ($sortedDependencies as $dependency) {
            $dependencyDetails = $this->getDetail($dependency);
            if ($dependencyDetails === null) {
                throw ModuleException::cannotFindModuleDetails($dependency);
            }

            if (! $dependencyDetails->isInstalled()) {
                $this->install($dependency);
            } elseif (! $dependencyDetails->isUpdated()) {
                $this->update($dependency);
            }
        }
    }

    /**
     * Sort module dependencies.
     *
     * @param string $moduleId (example: centreon-license-manager)
     * @param string[] $alreadyProcessed
     *
     * @throws ModuleException
     *
     * @return string[]
     */
    private function getSortedDependencies(
        string $moduleId,
        array $alreadyProcessed = []
    ) {
        $dependencies = [];

        if (in_array($moduleId, $alreadyProcessed, true)) {
            return $dependencies;
        }

        $alreadyProcessed[] = $moduleId;

        $moduleDetails = $this->getDetail($moduleId);
        if ($moduleDetails === null) {
            throw ModuleException::moduleIsMissing($moduleId);
        }

        foreach ($moduleDetails->getDependencies() as $dependency) {
            $dependencies[] = $dependency;
            $dependencyDetails = $this->getDetail($dependency);
            if (! $dependencyDetails){
                throw ModuleException::moduleIsMissing($dependency);
            }
            $dependencies = array_unique([
                ...$this->getSortedDependencies($dependencyDetails->getId(), $alreadyProcessed),
                ...$dependencies,
            ]);
        }

        return $dependencies;
    }

    /**
     * Validate requirements before remove (dependencies).
     *
     * @param string $moduleId (example: centreon-license-manager)
     *
     * @throws ModuleException
     */
    private function validateRemovalRequirementsOrFail(string $moduleId): void
    {
        $dependenciesToRemove = [];

        $modules = $this->getList();
        foreach ($modules as $module) {
            if ($module->isInstalled() && in_array($moduleId, $module->getDependencies(), true)) {
                $dependenciesToRemove[] = $module->getName();
            }
        }

        if ($dependenciesToRemove !== []) {
            throw ModuleException::modulesNeedToBeRemovedFirst($dependenciesToRemove);
        }
    }
}
