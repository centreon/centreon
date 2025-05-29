import { Column, Group, InputProps } from '@centreon/ui';
import { ObjectSchema } from 'yup';

export type NamedEntity = {
  id: number;
  name: string;
};

export enum ResourceType {
  Host = 'host',
  Service = 'service',
  HostGroup = 'host group',
  ServiceGroup = 'service group',
  AdditionalConfigurations = 'additional configuration'
}

export interface Form {
  inputs: Array<InputProps>;
  groups: Array<Group>;
  validationSchema: ObjectSchema<object>;
  defaultValues: object;
}

export type Filters = {
  name: string;
  enabeld?: boolean;
  disabled?: boolean;
} & Record<string, string | boolean>;

export interface Actions {
  delete?: boolean;
  duplicate?: boolean;
  enableDisable?: boolean;
  massive?:
    | boolean
    | {
        delete?: boolean;
        duplicate?: boolean;
        enable?: boolean;
        disable?: boolean;
      };
  edit?: boolean;
  viewDetails?: boolean;
}

export interface ConfigurationBase {
  resourceType: ResourceType;
  columns: Array<Column>;
  form: Form;
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
  filtersInitialValues: Filters;
  defaultSelectedColumnIds: Array<string>;
  actions?: Actions;
}

export enum FieldType {
  Text = 'text',
  Status = 'status',
  MultiAutocomplete = 'multiAutocomplete',
  MultiConnectedAutocomplete = 'multiConnectedAutocomplete'
}

export interface Endpoints {
  getAll: string;
  getOne?: ({ id }) => string;
  deleteOne?: ({ id }) => string;
  delete?: string;
  duplicate?: string;
  enable?: string;
  disable?: string;
  create?: string;
  update?: ({ id }) => string;
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
  options?: Array<{ id: number | string; name: string }>;
  getEndpoint?: (parametes) => string;
}

export interface Configuration {
  resourceType: ResourceType | null;
  api: APIType | null;
  filtersConfiguration?: Array<FilterConfiguration>;
  filtersInitialValues: Filters;
  defaultSelectedColumnIds: Array<string>;
  actions?: Actions;
}
