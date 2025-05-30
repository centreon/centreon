<?php

/*
 * Centreon
 *
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Module\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Module\Application\Repository\ModuleInformationRepositoryInterface;
use Core\Module\Domain\ModuleInformation;

class DbReadModuleinformationRepository extends DatabaseRepository implements ModuleInformationRepositoryInterface
{
	public function findByName(string $name): ?ModuleInformation
	{
        $query = $this->queryBuilder
            ->select('name', 'rname', 'mod_release')
            ->from('modules_informations')
            ->where($this->queryBuilder->expr()->equal('name', ':name'))
            ->getQuery();


        $queryParameters = QueryParameters::create([
            QueryParameter::string('name', $name),
		]);
		$result = $this->connection->fetchAssociative($query, $queryParameters);

		if ($result !== []) {
			return new ModuleInformation(
				packageName: $result['name'],
				displayName: $result['rname'],
				version: $result['mod_release']
			);
		}
        return null;
	}
}
