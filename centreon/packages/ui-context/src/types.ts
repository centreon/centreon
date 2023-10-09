import { Dispatch, SetStateAction } from 'react';

export enum ListingVariant {
  compact = 'compact',
  extended = 'extended'
}

export interface User {
  alias: string;
  default_page?: string | null;
  isExportButtonEnabled: boolean;
  locale: string;
  name: string;
  themeMode?: ThemeMode;
  timezone: string;
  use_deprecated_pages: boolean;
  user_interface_density: ListingVariant;
}

export enum ThemeMode {
  dark = 'dark',
  light = 'light'
}

export interface CloudServices {
  areCloudServicesEnabled: boolean;
  setAreCloudServicesEnabled: Dispatch<SetStateAction<boolean>>;
}

export interface Acknowledgement {
  force_active_checks: boolean;
  notify: boolean;
  persistent: boolean;
  sticky: boolean;
  with_services: boolean;
}

export type UserContext = {
  acknowledgement: Acknowledgement;
  acl: Acl;
  cloudServices: CloudServices | undefined;
  downtime: Downtime;
  refreshInterval: number;
  resourceStorageOptimizationMode: boolean;
} & User;

export interface ActionAcl {
  acknowledgement: boolean;
  check: boolean;
  comment: boolean;
  downtime: boolean;
  submit_status: boolean;
}

export interface Actions {
  host: ActionAcl;
  service: ActionAcl;
}

export interface Acl {
  actions: Actions;
}

export interface Downtime {
  duration: number;
  fixed: boolean;
  with_services: boolean;
}

export interface FeatureFlags {
  adExclusionPeriods?: boolean;
  dashboard?: boolean;
  notification?: boolean;
  resourceStatusTreeView?: boolean;
  vault?: boolean;
}

export interface PlatformFeatures {
  featureFlags: FeatureFlags;
  isCloudPlatform: boolean;
}
