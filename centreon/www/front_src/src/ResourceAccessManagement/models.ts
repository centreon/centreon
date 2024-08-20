export enum ModalMode {
  Create = 'create',
  Edit = 'edit'
}

export enum ResourceTypeEnum {
  All = 'all',
  BusinessView = 'business_view',
  Empty = '',
  Host = 'host',
  HostCategory = 'host_category',
  HostGroup = 'hostgroup',
  MetaService = 'meta_service',
  Service = 'service',
  ServiceCategory = 'service_category',
  ServiceGroup = 'servicegroup'
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
  allOfResourceType: boolean;
  resourceType: ResourceTypeEnum;
  resources: Array<NamedEntity>;
};

export type ResourceAccessRule = ResourceAccessRuleType & {
  allContactGroups: boolean;
  allContacts: boolean;
  contactGroups: Array<NamedEntity>;
  contacts: Array<NamedEntity>;
  datasetFilters: Array<Array<Dataset>>;
};

export type DatasetFilter = {
  datasetFilter: DatasetFilter | null;
  resourceType: ResourceTypeEnum;
  resources: Array<NamedEntity>;
};

export type GetResourceAccessRule = ResourceAccessRuleType & {
  contactGroups: {
    all: boolean;
    values: Array<NamedEntity>;
  };
  contacts: {
    all: boolean;
    values: Array<NamedEntity>;
  };
  datasetFilters: Array<DatasetFilter>;
};

export type SortOrder = 'asc' | 'desc';

export type NamedEntity = {
  id: number;
  name: string;
};

export enum DeleteType {
  MultipleItems,
  SingleItem
}

export interface DeleteResourceAccessRuleType {
  deleteType: DeleteType;
  id: number | Array<number> | null;
  name?: string;
}

export interface DuplicateResourceAccessRuleType {
  id: number | null;
  rule?: ResourceAccessRule;
}
