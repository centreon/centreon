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

namespace Core\Common\Infrastructure;

use Core\Module\Application\Repository\ModuleInformationRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Attribute\AsRoutingConditionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;

#[AsRoutingConditionService(alias: 'route_checker')]
final class RouteChecker
{
	public function __construct(private ModuleInformationRepositoryInterface $moduleInformationRepository)
	{
	}

	public function check(Request $request): bool
	{
		$path = $request->getPathInfo();
		if (preg_match('#/api/(latest|v[\d\.]+)/([^/]+)/#', $path, $matches)) {
		    $modulePath = $matches[2]; // contient "bam"
		} else {
			throw new \Exception("unable to retrieve module from URI");
		}
		// This Logic should be deported in each modules with a common Interface and services
		if ($modulePath === "bam") {
			$moduleName = "centreon-bam-server";
		}	
		$moduleInformation = $this->moduleInformationRepository->findByName($moduleName);
		if ($moduleInformation === null) {
			throw new \Exception("no module found");
		}
		$process = Process::fromShellCommandline("rpm -qa | grep " . escapeshellarg($moduleName));
		$process->run();
		$version = $process->getOutput();
		if (preg_match('/\b(\d+\.\d+\.\d+)\b/', $version, $matches)) {
			$version = $matches[1];
		} else {
    		echo "Version non trouvée.\n";
		}
		/**
		 * If a new version is available we return false as we want the Checker to fail
		 * If no new version are available so we return true so the route will be available
		 */
		return ! $moduleInformation->hasANewVersionAvailable($version);
	}
}
