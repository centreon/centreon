import { Column } from '@centreon/ui';

export enum ResourceType {
  Host = 'Host',
  Service = 'Service',
  HostGroup = 'Host group',
  ServiceGroup = 'Service group'
}

export interface Endpoints {
  getAll: string;
  getOne: ({ id }) => string;
  deleleteOne: ({ id }) => string;
  delete: string;
  duplicate: string;
  enable: string;
  disable: string;
}

export interface ConfigurationBase {
  resourceType: ResourceType;
  Form: JSX.Element;
  columns: Array<Column>;
}

export interface Configuration {
  resourceType: ResourceType | null;
  endpoints: Endpoints | null;
}
