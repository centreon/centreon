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
  modules: Record<string, Version>;
  web: Version;
  widgets: Record<string, Version>;
}

export interface FeatureFlags {
  adExclusionPeriods?: boolean;
  dashboard?: boolean;
  notification?: boolean;
  resourceStatusThreeView?: boolean;
  vault?: boolean;
}

export interface PlatformFeatures {
  featuresFlags: FeatureFlags;
  isCloudPlatform: boolean;
}
