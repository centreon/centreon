import { Dispatch, SetStateAction } from 'react';

export enum ListingVariant {
  compact = 'compact',
  extended = 'extended'
}

export enum DashboardGlobalRole {
  administrator = 'administrator',
  creator = 'creator',
  viewer = 'viewer'
}

export interface DashboardRolesAndPermissions {
  createDashboards: boolean;
  globalUserRole: DashboardGlobalRole;
  manageAllDashboards: boolean;
  viewDashboards: boolean;
}

export interface User {
  alias: string;
  canManageApiTokens: boolean;
  dashboard?: DashboardRolesAndPermissions | null;
  default_page?: string | null;
  id?: number;
  isAdmin?: boolean;
  isExportButtonEnabled: boolean;
  locale: string;
  name: string;
  themeMode?: ThemeMode;
  timezone: string;
  use_deprecated_pages: boolean;
  user_interface_density: ListingVariant;
}

export interface Profile {
  favoriteDashboards?: Array<number>;
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
  notification?: boolean;
  vault?: boolean;
}

export interface PlatformFeatures {
  featureFlags: FeatureFlags;
  isCloudPlatform: boolean;
}

export interface AdditionalResource {
  baseEndpoint: string;
  defaultMonitoringParameter?: Record<string, boolean | number | string>;
  label: string;
  resourceType: string;
}

interface FederatedComponentsConfiguration {
  federatedComponents: Array<string>;
  panelMinHeight?: number;
  panelMinWidth?: number;
  path: string;
  title?: string;
}

interface PageComponent {
  children?: string;
  component: string;
  featureFlag?: string;
  route: string;
}

export interface FederatedModule {
  federatedComponentsConfiguration: Array<FederatedComponentsConfiguration>;
  federatedPages: Array<PageComponent>;
  moduleFederationName: string;
  moduleName: string;
  preloadScript?: string;
  remoteEntry: string;
  remoteUrl?: string;
}

interface Version {
  fix: string;
  major: string;
  minor: string;
  version: string;
}

export interface PlatformVersions {
  modules: Record<string, Version>;
  web: Version;
  widgets: Record<string, Version | null>;
}
