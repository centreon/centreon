export interface User {
  locale: string;
  timezone: string;
  name: string;
  alias: string;
}

export type UserContext = {
  acl: Acl;
} & User;

export interface ActionAcl {
  check: boolean;
  acknowledgement: boolean;
  downtime: boolean;
  submit_status: boolean;
}

export interface Actions {
  service: ActionAcl;
  host: ActionAcl;
}

interface Acl {
  actions: Actions;
}
