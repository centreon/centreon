export interface User {
  alias: string;
  locale: string;
  name: string;
  timezone: string;
}

export type UserContext = {
  acl: Acl;
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

interface Acl {
  actions: Actions;
}

export interface Downtime {
  default_duration: number;
}
