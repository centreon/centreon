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

namespace App\Upgrade\Infrastructure\Doctrine;

use Symfony\Component\Filesystem\Filesystem;

final readonly class MigrationGenerator
{
    private const string TEMPLATE = <<<'PHP'
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

        namespace {namespace};

        use App\Upgrade\Infrastructure\Doctrine\{base};
        use Doctrine\DBAL\Schema\Schema;

        final class {class} extends {base}
        {
            public function getDescription(): string
            {
                return '';
            }

            public function up(Schema $schema): void
            {
                // {hint}
                $this->addSql('');
            }

            public function revert(Schema $schema): void
            {
                // {hint}
                $this->addSql('');
            }
        }

        PHP;

    public function __construct(
        private MigrationSetRegistry $registry,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @return string the path of the generated migration
     */
    public function generate(string $set, bool $ddl, \DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        $migrationSet = $this->registry->get($set);
        $class = 'Version' . $now->setTimezone(new \DateTimeZone('UTC'))->format('YmdHis');
        $path = $migrationSet->directory . '/' . $class . '.php';

        if ($this->filesystem->exists($path)) {
            throw new \RuntimeException(\sprintf('The migration "%s" already exists.', $path));
        }

        $this->filesystem->dumpFile($path, strtr(self::TEMPLATE, [
            '{namespace}' => $migrationSet->namespace,
            '{class}' => $class,
            '{base}' => $ddl ? 'DdlMigration' : 'DmlMigration',
            '{hint}' => $ddl
                ? 'A single idempotent statement, run outside of any transaction.'
                : 'Data changes only, run in a single transaction.',
        ]));

        return $path;
    }
}
