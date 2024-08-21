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

namespace Centreon\Application\DataRepresenter\Topology;

use Centreon\Domain\Entity\Topology;
use JsonSerializable;

class NavigationList implements JsonSerializable
{
    /**
     * @param array<Topology> $entities
     * @param array<array{name: string, color: string, icon: string}> $navConfig
     * @param array<string> $enabledFeatureFlags
     */
    public function __construct(
        private array $entities,
        /**
         * Configurations from navigation.yml.
         */
        private array $navConfig = [],
        private array $enabledFeatureFlags = []
    )
    {
    }

    /**
     * @return array<array{name: string, color: string, icon: string}>
     */
    public function getNavConfig(): array
    {
        return $this->navConfig;
    }

    /**
     * JSON serialization of entity.
     *
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        $groups = $this->extractGroups($this->entities);
        $tree = $this->generateTreeLevels($this->entities, $groups);

        return $this->removeKeysFromArray($tree);
    }

    /**
     * Get navigation items color for page.
     *
     * @param int|string $pageId The page id
     *
     * @return string color
     */
    protected function getColor(int|string $pageId): string
    {
        return $this->getNavConfig()[$pageId]['color']
            ?? $this->getNavConfig()['default']['color'];
    }

    /**
     * Get navigation items icons per page.
     *
     * @param int|string $pageId The page id
     *
     * @return string icon name
     */
    protected function getIcon(int|string $pageId): string
    {
        return $this->getNavConfig()[$pageId]['icon']
            ?? $this->getNavConfig()['default']['icon'];
    }

    /**
     * Extract groups from full array of topologies.
     *
     * @param array<Topology> $entities array of topologies
     *
     * @return array<mixed> of topologies
     */
    private function extractGroups(array $entities): array
    {
        $groups = [];
        foreach ($entities as $entity) {
            if (null === $entity->getTopologyPage() && $entity->getIsReact() === '0') {
                $groups[$entity->getTopologyParent()][$entity->getTopologyGroup()] = [
                    'name' => $entity->getTopologyName(),
                ];
            }
        }

        return $groups;
    }

    /**
     * Tells whether the Topology entity should be excluded because of feature flags.
     *
     * @param Topology $entity
     */
    private function isFeatureFlagExcluded(Topology $entity): bool
    {
        $flag = (string) $entity->getTopologyFeatureFlag();
        if ('' === $flag) {
            return false;
        }

        return ! in_array($flag, $this->enabledFeatureFlags, true);
    }

    /**
     * Generate level list of menu.
     *
     * @param array<Topology> $entities
     * @param array<mixed> $groups
     *
     * @return array<mixed>
     */
    private function generateTreeLevels(array $entities, array $groups): array
    {
        $tree = [];

        foreach ($entities as $entity) {
            if (
                null === ($topologyPage = $entity->getTopologyPage())
                || $this->isFeatureFlagExcluded($entity)
            ) {
                continue;
            }

            if (
                preg_match('/^(\d)$/', $topologyPage, $matches)
            ) {
                // LEVEL 1 (specific case where topology_id = topology_page)
                $tree[$matches[1]] = [
                    'page' => $topologyPage,
                    'label' => $entity->getTopologyName(),
                    'menu_id' => $entity->getTopologyName(),
                    'url' => $entity->getTopologyUrl(),
                    'color' => $this->getColor($topologyPage),
                    'icon' => $this->getIcon($topologyPage),
                    'children' => [],
                    'options' => $entity->getTopologyUrlOpt(),
                    'is_react' => (bool) $entity->getIsReact(),
                    'show' => (bool) $entity->getTopologyShow(),
                ];
            } elseif (
                preg_match('/^(\d)\d\d$/', $topologyPage, $matches)
                && ! empty($tree[$matches[1]])
            ) {
                // LEVEL 2
                $tree[$matches[1]]['children'][$topologyPage] = [
                    'page' => $topologyPage,
                    'label' => $entity->getTopologyName(),
                    'url' => $entity->getTopologyUrl(),
                    'groups' => [],
                    'options' => $entity->getTopologyUrlOpt(),
                    'is_react' => (bool) $entity->getIsReact(),
                    'show' => (bool) $entity->getTopologyShow(),
                ];
            } elseif (
                preg_match('/^(\d)(\d\d)\d\d$/', $topologyPage, $matches)
                && ! empty($tree[$matches[1]]['children'][$matches[1] . $matches[2]])
            ) {
                // LEVEL 3
                $levelOne = $matches[1];
                $levelTwo = $matches[1] . $matches[2];

                // generate the array for the item
                $levelThree = [
                    'page' => $topologyPage,
                    'label' => $entity->getTopologyName(),
                    'url' => $entity->getTopologyUrl(),
                    'options' => $entity->getTopologyUrlOpt(),
                    'is_react' => (bool) $entity->getIsReact(),
                    'show' => (bool) $entity->getTopologyShow(),
                ];

                // check if topology has group index
                $topologyGroup = $entity->getTopologyGroup();
                if (
                    null !== $topologyGroup
                    && isset($groups[$levelTwo][$topologyGroup])
                ) {
                    if (! isset($tree[$levelOne]['children'][$levelTwo]['groups'][$topologyGroup])) {
                        $tree[$levelOne]['children'][$levelTwo]['groups'][$topologyGroup] = [
                            'label' => $groups[$levelTwo][$topologyGroup]['name'],
                            'children' => [],
                        ];
                    }

                    $tree[$levelOne]['children'][$levelTwo]['groups'][$topologyGroup]['children'][] = $levelThree;
                } else {
                    if (! isset($tree[$levelOne]['children'][$levelTwo]['groups']['default'])) {
                        $tree[$levelOne]['children'][$levelTwo]['groups']['default'] = [
                            'label' => 'Main Menu',
                            'children' => [],
                        ];
                    }

                    $tree[$levelOne]['children'][$levelTwo]['groups']['default']['children'][] = $levelThree;
                }
            }
        }

        return $tree;
    }

    /**
     * Extract the array without keys to avoid serialization into objects.
     *
     * @param array<mixed> $tree
     *
     * @return array<mixed>
     */
    private function removeKeysFromArray(array $tree): array
    {
        foreach ($tree as &$value) {
            if (! empty($value['children'])) {
                foreach ($value['children'] as &$c) {
                    if (! empty($c['groups'])) {
                        $c['groups'] = array_values($c['groups']);
                    }
                }
                $value['children'] = array_values($value['children']);
            }
        }

        return array_values($tree);
    }
}
