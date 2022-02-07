import React from 'react';

export interface User {
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
  light = 'light',
}

export interface CloudServices {
  areCloudServicesEnabled: boolean;
  setAreCloudServicesEnabled: React.Dispatch<React.SetStateAction<boolean>>;
}

export interface Acknowledgement {
  persistent: boolean;
  sticky: boolean;
}

export type UserContext = {
  acknowledgement: Acknowledgement;
  acl: Acl;
  cloudServices: CloudServices | undefined;
  downtime: Downtime;
  refreshInterval: number;
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
  default_duration: number;
  default_fixed: boolean;
  default_with_services: boolean;
}
