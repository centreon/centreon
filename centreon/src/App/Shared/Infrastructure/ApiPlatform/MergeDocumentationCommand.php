<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
         *   paths: array<string, mixed>,
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
         *   paths: array{paths: array<string, mixed>},
         *   tags: array<array{name: string, ...}>,
         *   components: array{
         *     parameters: array<string, mixed>,
         *     responses: array<string, mixed>,
         *     schemas: array<string, mixed>,
         *   },
         * }
         */
        $newDoc = $this->normalizer->normalize(($this->openApiFactory)());

        $io->section('Paths...');
        foreach ($newDoc['paths']['paths'] as $url => $path) {
            /** @var string $url */
            $url = preg_replace('#/api/latest#', '', (string) $url);
            $io->text($url);

            if (! $override && isset($doc['paths'][$url]) && ! $this->askOverride($io, $url)) {
                continue;
            }

            $doc['paths'][$url] = $path;
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
}
