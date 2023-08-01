export interface PlatformInstallationStatus {
  hasUpgradeAvailable: boolean;
  isInstalled: boolean;
}

export interface Version {
  fix: string;
  major: string;
  minor: string;
  version: string;
}

export interface PlatformVersions {
  isCloudPlatform: boolean;
  modules: Record<string, Version>;
  web: Version;
  widgets: Record<string, Version>;
}
