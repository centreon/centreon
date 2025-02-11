import { Column } from '@centreon/ui';

export enum ResourceType {
  Host = 'host',
  Service = 'service',
  HostGroup = 'host group',
  ServiceGroup = 'service group'
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

export enum FieldType {
  Text = 'text',
  Status = 'status'
}

export interface FilterConfiguration {
  name: string;
  fieldName?: string;
  fieldType: FieldType;
}

export interface Configuration {
  resourceType: ResourceType | null;
  endpoints: Endpoints | null;
  filtersConfiguration?: Array<FilterConfiguration>;
  filtersInitialValues;
  defaultSelectedColumnIds: Array<string>;
}
