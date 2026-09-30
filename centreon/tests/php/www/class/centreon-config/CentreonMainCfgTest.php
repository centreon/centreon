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

beforeEach(function (): void {
    $this->originalEnv = $_ENV['IS_CLOUD_PLATFORM'] ?? null;
});

afterEach(function (): void {
    unset($_ENV['IS_CLOUD_PLATFORM']);
    if ($this->originalEnv !== null) {
        $_ENV['IS_CLOUD_PLATFORM'] = $this->originalEnv;
    }
});

/**
 * Build a CentreonMainCfg without touching the real database: bypass the
 * constructor (which opens a CentreonDB connection), populate the engine
 * defaults from $_ENV via setEngineOptions(), and inject a DB spy that records
 * every value bound on the cfg_nagios insert.
 *
 * @param array<string, mixed> $captured populated with the bound insert values
 */
function buildCentreonMainCfgWithDbSpy(array &$captured): CentreonMainCfg
{
    $insertStatement = new class ($captured) {
        /** @var array<string, mixed> */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        public function bindValue(string $param, mixed $value, int $type = PDO::PARAM_STR): bool
        {
            $this->captured[$param] = $value;

            return true;
        }

        public function execute(?array $params = null): bool
        {
            return true;
        }
    };

    $selectStatement = new class () {
        public function bindValue(string $param, mixed $value, int $type = PDO::PARAM_STR): bool
        {
            return true;
        }

        public function execute(?array $params = null): bool
        {
            return true;
        }

        // No source row: forces insertServerInCfgNagios to fall back on the defaults.
        public function rowCount(): int
        {
            return 0;
        }
    };

    $db = new class ($insertStatement, $selectStatement) {
        public function __construct(private object $insertStatement, private object $selectStatement)
        {
        }

        public function prepare(string $query): object
        {
            return str_contains($query, 'INSERT') ? $this->insertStatement : $this->selectStatement;
        }

        public function query(string $query): object
        {
            return new class () {
                /**
                 * @return array<string, int>
                 */
                public function fetch(): array
                {
                    return ['last_id' => 1];
                }
            };
        }
    };

    $reflection = new ReflectionClass(CentreonMainCfg::class);
    $cfg = $reflection->newInstanceWithoutConstructor();

    $setEngineOptions = $reflection->getMethod('setEngineOptions');
    $setEngineOptions->setAccessible(true);
    $setEngineOptions->invoke($cfg);

    $dbProperty = $reflection->getProperty('DB');
    $dbProperty->setAccessible(true);
    $dbProperty->setValue($cfg, $db);

    return $cfg;
}

test('it persists the disabled on-prem engine defaults as "0" instead of NULL', function (): void {
    unset($_ENV['IS_CLOUD_PLATFORM']);

    $captured = [];
    $cfg = buildCentreonMainCfgWithDbSpy($captured);

    $cfg->insertServerInCfgNagios(1, 2, 'TestPoller');

    // The regression guard: empty('0') === true would have nulled these.
    expect($captured[':enable_flap_detection'])->toBe('0')
        ->and($captured[':host_down_disable_service_checks'])->toBe('0');
});

test('it persists the enabled cloud engine defaults as "1"', function (): void {
    $_ENV['IS_CLOUD_PLATFORM'] = '1';

    $captured = [];
    $cfg = buildCentreonMainCfgWithDbSpy($captured);

    $cfg->insertServerInCfgNagios(1, 2, 'TestPoller');

    expect($captured[':enable_flap_detection'])->toBe('1')
        ->and($captured[':host_down_disable_service_checks'])->toBe('1');
});

test('it still binds empty string and null values as NULL', function (): void {
    unset($_ENV['IS_CLOUD_PLATFORM']);

    $captured = [];
    $cfg = buildCentreonMainCfgWithDbSpy($captured);

    $reflection = new ReflectionClass(CentreonMainCfg::class);
    $defaults = $reflection->getProperty('aInstanceDefaultValues');
    $defaults->setAccessible(true);
    $values = $defaults->getValue($cfg);
    $values['admin_email'] = '';
    $values['admin_pager'] = null;
    $defaults->setValue($cfg, $values);

    $cfg->insertServerInCfgNagios(1, 2, 'TestPoller');

    expect($captured[':admin_email'])->toBeNull()
        ->and($captured[':admin_pager'])->toBeNull();
});
