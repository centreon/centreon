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

export interface Filters {
  name: string;
  enabled: boolean;
  disabled: boolean;
}
