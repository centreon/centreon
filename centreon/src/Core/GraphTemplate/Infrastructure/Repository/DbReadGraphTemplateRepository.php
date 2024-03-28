<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\GraphTemplate\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\GraphTemplate\Application\Repository\ReadGraphTemplateRepositoryInterface;
use Core\GraphTemplate\Domain\Model\GraphTemplate;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _GraphTemplate array{
 *      graph_id: int,
 *      name: string,
 *      vertical_label: string,
 *      width: int,
 *      height: int,
 *      base: int,
 *      lower_limit: float|null,
 *      upper_limit: float|null,
 *      size_to_max: int,
 *      scaled: string,
 *      default_tpl1: string,
 * }
 */
class DbReadGraphTemplateRepository extends AbstractRepositoryRDB implements ReadGraphTemplateRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $id): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.giv_graphs_template
                WHERE graph_id = :id
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'graph_id',
            'name' => 'name',
        ]);

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect(
            <<<'SQL'
                SELECT
                    gt.graph_id,
                    gt.name,
                    gt.vertical_label,
                    gt.width,
                    gt.height,
                    gt.base,
                    gt.lower_limit,
                    gt.upper_limit,
                    gt.size_to_max,
                    gt.default_tpl1,
                    gt.scaled
                FROM `:db`.`giv_graphs_template` gt
                SQL
        );
        $sqlTranslator->translateForConcatenator($sqlConcatenator);
        $statement = $this->db->prepare($this->translateDbName((string) $sqlConcatenator));
        $sqlTranslator->bindSearchValues($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
        $sqlTranslator->calculateNumberOfRows($this->db);

        $graphTemplates = [];
        foreach ($statement as $result) {
            /** @var _GraphTemplate $result */
            $graphTemplates[] = new GraphTemplate(
                id: $result['graph_id'],
                name: $result['name'],
                verticalAxisLabel: $result['vertical_label'],
                width: $result['width'],
                height: $result['height'],
                base: $result['base'],
                gridLowerLimit: $result['lower_limit'],
                gridUpperLimit: $result['upper_limit'],
                isUpperLimitSizedToMax: (bool) $result['size_to_max'],
                isGraphScaled: (bool) $result['scaled'],
                isDefaultCentreonTemplate: (bool) $result['default_tpl1'],
            );
        }

        return $graphTemplates;
    }
}
