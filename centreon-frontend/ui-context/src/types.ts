export interface User {
  locale: string;
  timezone: string;
  name: string;
  alias: string;
}

export type UserContext = {
  acl: Acl;
  downtime: Downtime;
  refreshInterval: number;
} & User;

export interface ActionAcl {
  check: boolean;
  acknowledgement: boolean;
  downtime: boolean;
  submit_status: boolean;
  comment: boolean;
}

export interface Actions {
  service: ActionAcl;
  host: ActionAcl;
}

interface Acl {
  actions: Actions;
}

export interface Downtime {
  default_duration: number;
}
