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
  comment?: string | null;
  isShellEnabled: boolean;
  connector?: { id: string; name: string } | null;
}

export interface Payload {
  name: string;
  type: string;
  command_line: string;
  comment: string | null;
  is_shell_enabled: boolean;
  connector: string | null;
}

export interface Filters {
  name: string;
  enabled: boolean;
  disabled: boolean;
  type: Array<'Notification' | 'Check' | 'Miscellaneous' | 'Discovery'>;
  is_from_monitoring_connector: boolean;
}

export interface Plugin {
  name: string;
  commandLine: string;
  description?: string | null;
}
