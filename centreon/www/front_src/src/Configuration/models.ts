import { Column, Group, InputProps, Method } from '@centreon/ui';

import type { PrimitiveAtom } from 'jotai';
import type { JsonDecoder } from 'ts.data.json';
import { ObjectSchema } from 'yup';

export type ResourceRow = Record<string, unknown> & { id?: number | string };

export type NamedEntity = {
  id: number;
  name: string;
};

export enum ResourceType {
  Host = 'host',
  Service = 'service',
  HostGroup = 'host group',
  ServiceGroup = 'service group',
  AdditionalConfiguration = 'additional configuration',
  Command = 'command'
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
  delete?: (row?: ResourceRow) => boolean;
  duplicate?: (row?: ResourceRow) => boolean;
  enableDisable?: (row?: ResourceRow) => boolean;
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

export interface ConfigurationBase<TFilters> {
  resourceType: ResourceType;
  columns: Array<Column>;
  form: Form;
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
  filtersInitialValues: TFilters;
  defaultSelectedColumnIds: Array<string>;
  actions?: Actions;
  labels: {
    title: string;
    welcomePage: {
      title: string;
      description?: string;
      actions: {
        create: string;
      };
    };
  };
  columnsAtomKey: string;
  filtersAtomKey: string;
  selectedColumnIdsAtom: PrimitiveAtom<Array<string>>;
  filtersAtom: PrimitiveAtom<TFilters>;
  isWelcomePageDisplayedAtom: PrimitiveAtom<boolean>;
  navbar?: Array<{
    label: string;
    link: string;
  }>;
}

export enum FieldType {
  Text = 'text',
  Status = 'status',
  MultiAutocomplete = 'multiAutocomplete',
  MultiConnectedAutocomplete = 'multiConnectedAutocomplete',
  Checkbox = 'Checkbox',
  Checkboxes = 'Checkboxes'
}

export interface Endpoints {
  getAll: string;
  getOne?: ({ id }: { id: number | string }) => string;
  deleteOne?: ({ id }: { id: number | string }) => string;
  delete?: string;
  duplicate?: string;
  enable?: (params?: { id: number | string }) => string;
  disable?: (params?: { id: number | string }) => string;
  create?: string;
  update?: ({ id }: { id: number | string }) => string;
}

export interface APIType {
  endpoints: Endpoints | null;
  decoders?: {
    getOne?: JsonDecoder.Decoder<unknown>;
    getAll?: JsonDecoder.Decoder<unknown>;
  };
  adapter?: (data: unknown) => unknown;
  apiFormat?: 'Standard' | 'JSON-LD';
  methods?: {
    update?: Method;
    enable?: Method;
    disable?: Method;
  };
  isSingleDuplicate?: boolean;
}

export interface FilterConfiguration {
  name: string;
  fieldName?: string;
  fieldType: FieldType;
  options?: Array<{ id: number | string; name: string }>;
  getEndpoint?: (parameters: Record<string, unknown>) => string;
}

export interface Configuration {
  resourceType: ResourceType | null;
  api: APIType | null;
  filtersConfiguration?: Array<FilterConfiguration>;
  filtersInitialValues: Filters;
  defaultSelectedColumnIds: Array<string>;
  actions?: Actions;
}
