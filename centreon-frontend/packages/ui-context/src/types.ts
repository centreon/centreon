export interface User {
  alias: string;
  locale: string;
  name: string;
  timezone: string;
}

export type UserContext = {
  acl: Acl;
  downtime: Downtime;
  platformModules: PlatformModules;
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

interface Acl {
  actions: Actions;
}

export interface Downtime {
  default_duration: number;
}
interface ModuleLicense {
  status: boolean;
}

interface Module {
  fix: string;
  license: ModuleLicense | null;
  major: string;
  minor: string;
  version: string;
}

interface Modules {
  'centreon-autodiscovery-server'?: Module;
  'centreon-bam-server'?: Module;
  'centreon-license-manager'?: Module;
  'centreon-pp-manager'?: Module;
}
export interface PlatformModules {
  modules: Modules;
  web: Module;
}
