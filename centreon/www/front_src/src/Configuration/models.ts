import { Column, Group, InputProps } from '@centreon/ui';
import { ObjectSchema } from 'yup';

export enum ResourceType {
  Host = 'host',
  Service = 'service',
  HostGroup = 'host group',
  ServiceGroup = 'service group'
}

interface Form {
  inputs: Array<InputProps>;
  groups: Array<Group>;
  validationSchema: ObjectSchema<object>;
  defaultValues: object;
}

export interface ConfigurationBase {
  resourceType: ResourceType;
  columns: Array<Column>;
  form: Form;
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
  filtersInitialValues: Record<string, string | boolean>;
  defaultSelectedColumnIds: Array<string>;
}

export enum FieldType {
  Text = 'text',
  Status = 'status'
}

export interface Endpoints {
  getAll: string;
  getOne: ({ id }) => string;
  deleteOne: ({ id }) => string;
  delete: string;
  duplicate: string;
  enable: string;
  disable: string;
  create: string;
  update: ({ id }) => string;
}

export interface APIType {
  endpoints: Endpoints | null;
  decoders?: {
    getOne?;
    getAll?;
  };
  adapter?;
}

export interface FilterConfiguration {
  name: string;
  fieldName?: string;
  fieldType: FieldType;
}

export interface Configuration {
  resourceType: ResourceType | null;
  api: APIType | null;
  filtersConfiguration?: Array<FilterConfiguration>;
  filtersInitialValues: object;
  defaultSelectedColumnIds: Array<string>;
}
