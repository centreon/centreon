export type NamedEntity = {
  id: number;
  name: string;
};

export interface CommandsListItem extends NamedEntity {
  isActivated: boolean;
  hostsCount: number;
  hostTemplatesCount: number;
  servicesCount: number;
  serviceTemplatesCount: number;
  type: string;
  commandLine: string;
}

export interface Command {
  name: string;
  type: string;
  commandLine: string;
  comment?: string;
  isShellEnabled: boolean;
  connector: NamedEntity;
}

export interface Payload {
  name: string;
  type: string;
  command_line: string;
  comment?: string;
  is_shell_enabled: boolean;
  connector: NamedEntity;
}

export interface Filters {
  name: string;
  enabled: boolean;
  disabled: boolean;
}
