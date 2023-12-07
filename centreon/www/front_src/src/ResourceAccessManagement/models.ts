import { Column } from '@centreon/ui';

export enum ModalMode {
  Create = 'create',
  Edit = 'edit'
}

export enum ResourceTypeEnum {
  Host = 'Host',
  HostCategory = 'Host Category',
  HostGroup = 'Host Group',
  MetaService = 'Meta Service',
  Service = 'Service',
  ServiceCategory = 'Service Category',
  ServiceGroup = 'Service Group'
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

export type Dataset = {
  resourceType: string;
  resources: Array<NamedEntity>;
};

export type NamedEntity = {
  id: number;
  name: string;
};

export type ResourceAccessRule = ResourceAccessRuleType & {
  contactGroups: Array<NamedEntity>;
  contacts: Array<NamedEntity>;
  datasets: Array<Array<Dataset>>;
};

export type SortOrder = 'asc' | 'desc';
