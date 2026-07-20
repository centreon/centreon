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

namespace App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:open-api:merge')]
final readonly class MergeDocumentationCommand
{
    private string $documentationPath;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        private readonly OpenApiFactoryInterface $openApiFactory,
        private DecoderInterface $decoder,
        private readonly NormalizerInterface $normalizer,
    ) {
        $this->documentationPath = $this->projectDir . '/doc/API/centreon-api.yaml';
    }

    public function __invoke(SymfonyStyle $io, #[Option] bool $override = false): int
    {
        $filesystem = new Filesystem();

        $io->title('Merge new OpenAPI documentation');

        /**
         * @var array{
         *   paths: array<string, array<string, mixed>>,
         *   tags: array<array{name: string, ...}>,
         *   components: array{
         *     parameters: array<string, mixed>,
         *     responses: array<string, mixed>,
         *     schemas: array<string, mixed>,
         *   },
         * }
         */
        $doc = $this->decoder->decode($filesystem->readFile($this->documentationPath), 'yaml');

        /**
         * @var array{
         *   paths: array<string, array<string, mixed>>,
         *   tags: array<array{name: string, ...}>,
         *   components: array{
         *     parameters: array<string, mixed>,
         *     responses: array<string, mixed>,
         *     schemas: array<string, mixed>,
         *   },
         * }
         */
        $newDoc = $this->normalizer->normalize(($this->openApiFactory)(), 'json', ['spec_version' => '3.0.0']);
        /** @var array{paths: array<string, array<string, mixed>>, tags: array<array{name: string, ...}>, components: array{parameters: array<string, mixed>, responses: array<string, mixed>, schemas: array<string, mixed>}} $newDoc */
        $newDoc = $this->toOpenApi30($newDoc);

        $io->section('Paths...');
        foreach ($newDoc['paths'] as $url => $path) {
            /** @var string $url */
            $url = preg_replace('#/api/latest#', '', (string) $url) ?? '';
            $io->text($url);

            if (! isset($doc['paths'][$url])) {
                $doc['paths'][$url] = $path;
                continue;
            }

            foreach ($path as $method => $operation) {
                $label = mb_strtoupper($method) . ' ' . $url;
                $io->text('  ' . $label);

                if (! $override && isset($doc['paths'][$url][$method]) && ! $this->askOverride($io, $label)) {
                    continue;
                }

                $doc['paths'][$url][$method] = $operation;
            }
        }

        $io->section('Tags...');
        $actualTagNames = array_column($doc['tags'], 'name');
        foreach ($newDoc['tags'] as $newTag) {
            $tagName = $newTag['name'];
            $io->text($tagName);

            if (! $override && \in_array($tagName, $actualTagNames, true) && ! $this->askOverride($io, $tagName)) {
                continue;
            }

            $found = false;
            foreach ($doc['tags'] as $key => $tag) {
                if ($tag['name'] === $tagName) {
                    $doc['tags'][$key] = $newTag;
                    $found = true;

                    break;
                }
            }

            if (! $found) {
                $doc['tags'][] = $newTag;
                $actualTagNames[] = $tagName;
            }
        }

        $io->section('Parameters...');
        foreach ($newDoc['components']['parameters'] as $name => $parameter) {
            $io->text($name);

            if (! $override && isset($doc['components']['parameters'][$name]) && ! $this->askOverride($io, $name)) {
                continue;
            }

            $doc['components']['parameters'][$name] = $parameter;
        }

        $io->section('Responses...');
        foreach ($newDoc['components']['responses'] as $name => $response) {
            $io->text($name);

            if (! $override && isset($doc['components']['responses'][$name]) && ! $this->askOverride($io, $name)) {
                continue;
            }

            $doc['components']['responses'][$name] = $response;
        }

        $io->section('Schemas...');
        foreach ($newDoc['components']['schemas'] as $name => $schema) {
            $io->text($name);

            if (! $override && isset($doc['components']['schemas'][$name]) && ! $this->askOverride($io, $name)) {
                continue;
            }

            $doc['components']['schemas'][$name] = $schema;
        }

        $yaml = Yaml::dump($doc, 10, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NUMERIC_KEY_AS_STRING);

        $filesystem->dumpFile($this->documentationPath, $yaml);

        return Command::SUCCESS;
    }

    private function askOverride(SymfonyStyle $io, string $subject): bool
    {
        return $io->choice(\sprintf('"%s" already exist.', $subject), ['skip', 'override'], 'override') === 'override';
    }

    /**
     * Recursively convert OpenAPI 3.1 null patterns to OpenAPI 3.0 nullable: true.
     *
     * ApiPlatform generates 3.1-style schemas (type arrays, anyOf with null) regardless
     * of spec_version context — LegacyOpenApiNormalizer only partially converts them.
     * This method handles the two patterns that must become nullable: true in 3.0:
     *   - type: ['string', 'null']           → type: string,  nullable: true
     *   - anyOf: [{...}, {type: 'null'}]     → merged schema, nullable: true
     *
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function toOpenApi30(array $schema): array
    {
        // type: ['string', 'null'] → type: string, nullable: true
        if (isset($schema['type']) && \is_array($schema['type'])) {
            $types = array_values(array_filter($schema['type'], static fn ($type): bool => $type !== 'null'));
            if (\count($types) < \count($schema['type'])) {
                $schema['nullable'] = true;
                $schema['type'] = \count($types) === 1 ? $types[0] : $types;
                if ($schema['type'] === []) {
                    unset($schema['type']);
                }
            }
        }

        // anyOf: [{...}, {type: 'null'}] → merge inner schema + nullable: true
        if (isset($schema['anyOf']) && \is_array($schema['anyOf'])) {
            $nullKey = null;
            foreach ($schema['anyOf'] as $nullIdx => $sub) {
                if (\is_array($sub) && $sub === ['type' => 'null']) {
                    $nullKey = $nullIdx;
                    break;
                }
            }
            if ($nullKey !== null) {
                unset($schema['anyOf'][$nullKey]);
                $remaining = array_values($schema['anyOf']);
                $schema['nullable'] = true;
                if (\count($remaining) === 1) {
                    unset($schema['anyOf']);
                    /** @var array<mixed> $firstSchema */
                    $firstSchema = $remaining[0];
                    if (isset($firstSchema['$ref'])) {
                        // $ref cannot have sibling keys in OAS 3.0; wrap in allOf
                        $schema = ['allOf' => [$firstSchema, $schema]];
                    } else {
                        $schema = array_merge($firstSchema, $schema);
                    }
                } else {
                    $schema['anyOf'] = $remaining;
                }
            }
        }

        foreach ($schema as $key => $value) {
            if (\is_array($value)) {
                $schema[$key] = $this->toOpenApi30($value);
            }
        }

        return $schema;
    }
}
