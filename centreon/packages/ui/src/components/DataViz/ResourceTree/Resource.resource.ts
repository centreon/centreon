export type Resource = {
  id: string;
  name: string;
  type: 'host' | 'service';
};

export type HostResource = Resource & {
  categories: Array<ResourceCategory>;
  children: Array<HostResource>;
  groups: Array<ResourceGroup>;
  parents: Array<HostResource>;
  type: 'host';
};

export type ServiceResource = Resource & {
  categories: Array<ResourceCategory>;
  groups: Array<ResourceGroup>;
  host: HostResource;
  type: 'service';
};

export const isHostResource = (resource: unknown): resource is HostResource =>
  (resource as HostResource).type === 'host';

export const isServiceResource = (
  resource: unknown
): resource is ServiceResource =>
  (resource as ServiceResource).type === 'service';

export type ResourceCategory = {
  id: string;
  name: string;
  type: 'host' | 'service';
};

export type ResourceGroup = {
  id: string;
  name: HostGroupResourceName | string;
  type: 'host' | 'service';
};

export type HostGroupResourceName =
  'server'
  | 'cloud'
  | 'storage'
  | 'router'
  | 'firewall'
  | 'other';

export type ResourceStatus =
  'neutral'
  | 'ok'
  | 'warn'
  | 'error'
