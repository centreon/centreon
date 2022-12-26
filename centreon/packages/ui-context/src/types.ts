import React from 'react';

export interface User {
  ResourceStatusViewMode: ResourceStatusViewMode;
  alias: string;
  default_page?: string | null;
  isExportButtonEnabled: boolean;
  locale: string;
  name: string;
  themeMode?: ThemeMode;
  timezone: string;
  use_deprecated_pages: boolean;
}

export enum ThemeMode {
  dark = 'dark',
  light = 'light'
}

export enum ResourceStatusViewMode {
  compact = 'compact',
  extended = 'extended'
}

export interface CloudServices {
  areCloudServicesEnabled: boolean;
  setAreCloudServicesEnabled: React.Dispatch<React.SetStateAction<boolean>>;
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
