import { Column, Group, InputProps, Method } from '@centreon/ui';

import type { PrimitiveAtom } from 'jotai';
import type { ComponentType } from 'react';
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

// An inline action a module adds to the row, next to the shared duplicate and
// delete icons. Unlike those, its visibility is entirely the module's call, so
// it can stay available to a user with no write access.
export interface RowAction {
  Icon: ComponentType<{ className?: string }>;
  dataTestId: (row: ResourceRow) => string;
  isVisible?: (row: ResourceRow) => boolean;
  label: string;
  onClick: (row: ResourceRow) => void;
}

// An entry a module appends to the More actions menu, after the shared ones.
export interface MassiveAction {
  Icon: ComponentType;
  dataTestId: string;
  label: string;
  onClick: (rows: Array<ResourceRow>) => void;
}

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
  rowActions?: Array<RowAction>;
  massiveActions?: Array<MassiveAction>;
  // Keep the per-row actions and the enable/disable toggle on screen for a user
  // without write access, letting each cell decide what it shows. Modules that
  // gate their row actions on write access alone leave this off.
  rowActionsWithoutWriteAccess?: boolean;
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
  filtersPanelWidth?: number;
}

export enum FieldType {
  Text = 'text',
  Status = 'status',
  MultiAutocomplete = 'multiAutocomplete',
  MultiConnectedAutocomplete = 'multiConnectedAutocomplete',
  SingleConnectedAutocomplete = 'singleConnectedAutocomplete',
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
  // Overrides the default `./api/latest` of `customFetch`. Applies to `getAll`.
  baseEndpoint?: string;
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
  // Overrides the default `./api/latest` of `customFetch`. API Platform selector
  // endpoints are only aliased under that prefix when allowlisted, so most need
  // `./api`.
  baseEndpoint?: string;
  // The connected autocomplete reads `{ result, meta }`; a Hydra selector needs
  // a decoder built with `apiFormat: 'JSON-LD'` to get there.
  decoder?: JsonDecoder.Decoder<unknown>;
}

export interface Configuration {
  resourceType: ResourceType | null;
  api: APIType | null;
  filtersConfiguration?: Array<FilterConfiguration>;
  filtersInitialValues: Filters;
  defaultSelectedColumnIds: Array<string>;
  actions?: Actions;
  filtersPanelWidth?: number;
}
