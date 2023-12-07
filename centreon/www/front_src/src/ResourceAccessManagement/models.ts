import { Column } from '@centreon/ui';

export enum ModalMode {
  Create = 'create',
  Edit = 'edit'
}

export interface Listing {
  changePage: (page: number) => void;
  changeSort: ({
    sortField,
    sortOrder
  }: {
    sortField: string;
    sortOrder: SortOrder;
  }) => void;
  columns: Array<Column>;
  data?: ResourceAccessRuleListingType;
  loading: boolean;
  page: number | undefined;
  predefinedRowsSelection: Array<{
    label: string;
    rowCondition: (row: ResourceAccessRuleType) => boolean;
  }>;
  resetColumns: () => void;
  selectedColumnIds: Array<string>;
  selectedRows: Array<ResourceAccessRuleType>;
  setLimit: (limit: number | undefined) => void;
  setSelectedColumnIds: (selectedColumnIds: Array<string>) => void;
  setSelectedRows: (selectedRows: Array<ResourceAccessRuleType>) => void;
  sortF: string;
  sortO: SortOrder;
}

export interface MetaType {
  limit: number;
  page: number;
  search?: Record<string, unknown>;
  sort_by?: Record<string, unknown>;
  total: number;
}

export interface ResourceAccessRuleListingType {
  meta: MetaType;
  result: Array<ResourceAccessRuleType>;
}

export type ResourceAccessRuleType = {
  description: string;
  id: number;
  isActivated: boolean;
  name: string;
};

export type Resource = {
  resourceType: string;
  resources: Array<number>;
};

export type Contact = {
  id: number;
  name: string;
};

export type ResourceAccessRule = ResourceAccessRuleType & {
  contactGroups: Array<Contact>;
  contacts: Array<Contact>;
  datasets: Array<Array<Resource>>;
};

export type SortOrder = 'asc' | 'desc';
