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

namespace Core\Module\Domain;

final readonly class ModuleInformation
{
	public function __construct(
		private string $packageName,
		private string $displayName,
		private string $version,
	) {
	}

	public function getPackageName(): string
	{
		return $this->packageName;
	}

	public function getDisplayName(): string
	{
		return $this->displayName;
	}

	public function getVersion(): string
	{
		return $this->version;
	}

	public function hasANewVersionAvailable(string $upcomingVersion): bool
	{
		return version_compare($upcomingVersion, $this->version, '>');
	}
}
