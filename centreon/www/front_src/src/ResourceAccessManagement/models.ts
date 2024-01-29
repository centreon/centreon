import { Column } from '@centreon/ui';

export enum ModalMode {
  Create = 'create',
  Edit = 'edit'
}

export enum ResourceTypeEnum {
  Host = 'host',
  HostCategory = 'host_category',
  HostGroup = 'hostgroup',
  MetaService = 'meta_service',
  Service = 'service',
  ServiceCategory = 'service_category',
  ServiceGroup = 'servicegroup'
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
  id?: number;
  isActivated: boolean;
  name: string;
};

export type Dataset = {
  resourceType: ResourceTypeEnum | undefined;
  resources: Array<number>;
};

export type ResourceAccessRule = ResourceAccessRuleType & {
  contactGroups: Array<number>;
  contacts: Array<number>;
  datasetFilters: Array<Array<Dataset>>;
};

export type SortOrder = 'asc' | 'desc';
