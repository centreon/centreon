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

declare(strict_types=1);

namespace App\Upgrade\Infrastructure\Dbal;

use App\Upgrade\Domain\Repository\WidgetRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

final readonly class DbalWidgetRepository implements WidgetRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $configConnection,
        #[Autowire(param: 'upgrade.widgets_dir')]
        private string $widgetsDir,
        private LoggerInterface $logger,
    ) {
    }

    public function updateAll(): void
    {
        $installedWidgets = $this->getInstalledWidgetVersions();
        $codeWidgets = $this->loadWidgetConfigurations();

        foreach ($installedWidgets as $directory => $widgetInfo) {
            if (! isset($codeWidgets[$directory])) {
                continue;
            }

            $codeVersion = trim(mb_strtolower($this->asString($codeWidgets[$directory]['version'] ?? '')));
            $dbVersion = trim(mb_strtolower($widgetInfo['version']));

            if ($codeVersion === $dbVersion) {
                continue;
            }

            $this->logger->info('Upgrading widget', [
                'directory' => $directory,
                'from' => $dbVersion,
                'to' => $codeVersion,
            ]);

            $this->upgradeWidgetConfiguration($codeWidgets[$directory]);
            $this->upgradeWidgetPreferences(
                (int) $widgetInfo['widget_model_id'],
                $codeWidgets[$directory],
            );
        }
    }

    /**
     * @return array<string, array{widget_model_id: string, version: string}>
     */
    private function getInstalledWidgetVersions(): array
    {
        $rows = $this->configConnection->fetchAllAssociative(
            'SELECT `widget_model_id`, `directory`, `version` FROM `widget_models`'
        );

        $result = [];
        foreach ($rows as $row) {
            $directory = is_string($row['directory']) ? $row['directory'] : '';
            if ($directory !== '') {
                $result[$directory] = [
                    'widget_model_id' => $this->asString($row['widget_model_id']),
                    'version' => is_string($row['version']) ? $row['version'] : '',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadWidgetConfigurations(): array
    {
        $result = [];

        if (! is_dir($this->widgetsDir)) {
            return $result;
        }

        $files = (new Finder())
            ->files()
            ->name('configs.xml')
            ->depth('== 1')
            ->in($this->widgetsDir);

        foreach ($files as $file) {
            $xml = @simplexml_load_file($file->getPathname());
            if ($xml === false) {
                continue;
            }

            $config = json_decode(json_encode($xml) ?: '{}', true);
            if (! is_array($config)) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $directory = $file->getRelativePath();
            $config['directory'] = $directory;
            $result[$directory] = $config;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $widgetConfig
     */
    private function upgradeWidgetConfiguration(array $widgetConfig): void
    {
        $this->configConnection->executeStatement(
            'UPDATE `widget_models` SET '
            . '`title` = :title, '
            . '`description` = :description, '
            . '`url` = :url, '
            . '`version` = :version, '
            . '`author` = :author, '
            . '`email` = :email, '
            . '`website` = :website, '
            . '`keywords` = :keywords, '
            . '`thumbnail` = :thumbnail, '
            . '`autoRefresh` = :autoRefresh '
            . 'WHERE `directory` = :directory',
            [
                'title' => $this->asString($widgetConfig['title'] ?? ''),
                'description' => $this->asString($widgetConfig['description'] ?? ''),
                'url' => $this->asString($widgetConfig['url'] ?? ''),
                'version' => $this->asString($widgetConfig['version'] ?? ''),
                'author' => $this->asString($widgetConfig['author'] ?? ''),
                'email' => $this->asString($widgetConfig['email'] ?? ''),
                'website' => $this->asString($widgetConfig['website'] ?? ''),
                'keywords' => $this->asString($widgetConfig['keywords'] ?? ''),
                'thumbnail' => $this->asString($widgetConfig['thumbnail'] ?? ''),
                'autoRefresh' => $this->asInt($widgetConfig['autoRefresh'] ?? 0),
                'directory' => $this->asString($widgetConfig['directory'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string, mixed> $widgetConfig
     */
    private function upgradeWidgetPreferences(int $widgetModelId, array $widgetConfig): void
    {
        if (! isset($widgetConfig['preferences'])) {
            return;
        }

        $types = $this->getWidgetParameterTypes();
        $existingParams = $this->getExistingWidgetParameters($widgetModelId);

        $processedNames = [];
        $preferences = $widgetConfig['preferences'];

        if (! is_array($preferences)) {
            return;
        }

        $preferenceGroups = $preferences['preference'] ?? null;
        if (! is_array($preferenceGroups)) {
            return;
        }
        // Normalize single preference to array of preferences.
        if (isset($preferenceGroups['@attributes'])) {
            $preferenceGroups = [$preferenceGroups];
        }

        $order = 1;
        foreach ($preferenceGroups as $preference) {
            if (! is_array($preference)) {
                continue;
            }

            $attrs = $preference['@attributes'] ?? $preference;
            if (! is_array($attrs)) {
                continue;
            }
            if (! isset($attrs['name'], $attrs['type'])) {
                continue;
            }

            $parameterName = $this->asString($attrs['name']);
            $typeName = $this->asString($attrs['type']);
            if (! isset($types[$typeName])) {
                $processedNames[] = $parameterName;
                $this->logger->warning('Unknown widget parameter type', ['type' => $typeName]);

                continue;
            }

            $paramData = [
                'name' => $this->asString($attrs['name']),
                'label' => $this->asString($attrs['label'] ?? $attrs['name']),
                'defaultValue' => $this->asString($attrs['defaultValue'] ?? ''),
                'requirePermission' => $this->asString($attrs['requirePermission'] ?? '0'),
                'header' => isset($attrs['header']) && $attrs['header'] !== '' ? $this->asString($attrs['header']) : null,
                'order' => $order,
                'type_id' => (int) $types[$typeName],
                'type_name' => $typeName,
            ];

            if (isset($existingParams[$paramData['name']])) {
                $this->updateWidgetParameter($widgetModelId, $paramData);
                $paramId = (int) $existingParams[$paramData['name']];
            } else {
                $paramId = $this->insertWidgetParameter($widgetModelId, $paramData);
            }

            $this->deleteWidgetParameterOptions($paramId);
            /** @var array<string, mixed> $preference */
            $this->insertWidgetParameterOptions($paramId, $paramData['type_name'], $preference);

            $processedNames[] = $paramData['name'];
            $order++;
        }

        foreach ($existingParams as $name => $paramId) {
            if (! in_array($name, $processedNames, true)) {
                $this->configConnection->executeStatement(
                    'DELETE FROM `widget_parameters` WHERE `parameter_id` = :id',
                    ['id' => (int) $paramId]
                );
            }
        }
    }

    /**
     * @return array<string, int> type name => field_type_id
     */
    private function getWidgetParameterTypes(): array
    {
        $rows = $this->configConnection->fetchAllAssociative(
            'SELECT `field_type_id`, `ft_typename` FROM `widget_parameters_field_type`'
        );

        $result = [];
        foreach ($rows as $row) {
            $name = is_string($row['ft_typename']) ? $row['ft_typename'] : '';
            if ($name !== '') {
                $result[$name] = $this->asInt($row['field_type_id']);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string> parameter_code_name => parameter_id
     */
    private function getExistingWidgetParameters(int $widgetModelId): array
    {
        $rows = $this->configConnection->fetchAllAssociative(
            'SELECT `parameter_id`, `parameter_code_name` FROM `widget_parameters` WHERE `widget_model_id` = :id',
            ['id' => $widgetModelId]
        );

        $result = [];
        foreach ($rows as $row) {
            $name = is_string($row['parameter_code_name']) ? $row['parameter_code_name'] : '';
            if ($name !== '') {
                $result[$name] = $this->asString($row['parameter_id']);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $paramData
     */
    private function updateWidgetParameter(int $widgetModelId, array $paramData): void
    {
        $this->configConnection->executeStatement(
            'UPDATE `widget_parameters` SET '
            . '`field_type_id` = :field_type_id, '
            . '`parameter_name` = :parameter_name, '
            . '`default_value` = :default_value, '
            . '`parameter_order` = :parameter_order, '
            . '`require_permission` = :require_permission, '
            . '`header_title` = :header_title '
            . 'WHERE `widget_model_id` = :widget_model_id AND `parameter_code_name` = :parameter_code_name',
            [
                'field_type_id' => $paramData['type_id'],
                'parameter_name' => $paramData['label'],
                'default_value' => $paramData['defaultValue'],
                'parameter_order' => $paramData['order'],
                'require_permission' => $paramData['requirePermission'],
                'header_title' => $paramData['header'],
                'widget_model_id' => $widgetModelId,
                'parameter_code_name' => $paramData['name'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $paramData
     */
    private function insertWidgetParameter(int $widgetModelId, array $paramData): int
    {
        $this->configConnection->executeStatement(
            'INSERT INTO `widget_parameters` '
            . '(`widget_model_id`, `field_type_id`, `parameter_name`, `parameter_code_name`, '
            . '`default_value`, `parameter_order`, `require_permission`, `header_title`) '
            . 'VALUES '
            . '(:widget_model_id, :field_type_id, :parameter_name, :parameter_code_name, '
            . ':default_value, :parameter_order, :require_permission, :header_title)',
            [
                'widget_model_id' => $widgetModelId,
                'field_type_id' => $paramData['type_id'],
                'parameter_name' => $paramData['label'],
                'parameter_code_name' => $paramData['name'],
                'default_value' => $paramData['defaultValue'],
                'parameter_order' => $paramData['order'],
                'require_permission' => $paramData['requirePermission'],
                'header_title' => $paramData['header'],
            ]
        );

        $result = $this->configConnection->fetchOne(
            'SELECT `parameter_id` FROM `widget_parameters` '
            . 'WHERE `parameter_code_name` = :name AND `widget_model_id` = :id',
            ['name' => $paramData['name'], 'id' => $widgetModelId]
        );

        return is_numeric($result) ? (int) $result : 0;
    }

    private function deleteWidgetParameterOptions(int $parameterId): void
    {
        $this->configConnection->executeStatement(
            'DELETE FROM `widget_parameters_multiple_options` WHERE `parameter_id` = :id',
            ['id' => $parameterId]
        );
        $this->configConnection->executeStatement(
            'DELETE FROM `widget_parameters_range` WHERE `parameter_id` = :id',
            ['id' => $parameterId]
        );
    }

    /**
     * @param array<string, mixed> $preference
     */
    private function insertWidgetParameterOptions(int $parameterId, string $typeName, array $preference): void
    {
        switch ($typeName) {
            case 'list':
            case 'sort':
                $this->insertMultipleOptions($parameterId, $preference);
                break;
            case 'range':
                $attrs = $preference['@attributes'] ?? $preference;
                if (is_array($attrs)) {
                    /** @var array<string, mixed> $attrs */
                    $this->insertRangeOption($parameterId, $attrs);
                }
                break;
        }
    }

    /**
     * @param array<string, mixed> $preference
     */
    private function insertMultipleOptions(int $parameterId, array $preference): void
    {
        if (! isset($preference['option'])) {
            return;
        }

        $options = $preference['option'];
        if (! is_array($options)) {
            return;
        }

        // Normalize single option to array of options.
        if (isset($options['@attributes'])) {
            $options = [$options];
        }

        foreach ($options as $option) {
            $opt = is_array($option) ? ($option['@attributes'] ?? $option) : null;
            if (! is_array($opt)) {
                continue;
            }
            if (! isset($opt['label'], $opt['value'])) {
                continue;
            }

            $this->configConnection->executeStatement(
                'INSERT INTO `widget_parameters_multiple_options` '
                . '(`parameter_id`, `option_name`, `option_value`) '
                . 'VALUES (:parameter_id, :option_name, :option_value)',
                [
                    'parameter_id' => $parameterId,
                    'option_name' => $this->asString($opt['label']),
                    'option_value' => $this->asString($opt['value']),
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function insertRangeOption(int $parameterId, array $attrs): void
    {
        if (! isset($attrs['min'], $attrs['max'], $attrs['step'])) {
            return;
        }

        $this->configConnection->executeStatement(
            'INSERT INTO `widget_parameters_range` '
            . '(`parameter_id`, `min_range`, `max_range`, `step`) '
            . 'VALUES (:parameter_id, :min_range, :max_range, :step)',
            [
                'parameter_id' => $parameterId,
                'min_range' => $this->asInt($attrs['min']),
                'max_range' => $this->asInt($attrs['max']),
                'step' => $this->asInt($attrs['step']),
            ]
        );
    }

    private function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function asInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
