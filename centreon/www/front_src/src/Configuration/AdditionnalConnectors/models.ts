export type NamedEntity = {
  id: number;
  name: string;
};

export enum ParameterKeys {
  name = 'vCenter name',
  password = 'Password',
  url = 'URL',
  username = 'Username'
}

export interface Parameter {
  [ParameterKeys.name]: string;
  [ParameterKeys.url]: string;
  [ParameterKeys.username]: string | null;
  [ParameterKeys.password]: string | null;
}

export interface AdditionalConnectorListItem extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity;
  description: string | null;
  name: string;
  type: string;
  updatedAt: string | null;
  updatedBy: NamedEntity | null;
}

export interface AdditionalConnectorConfiguration {
  description: null | string;
  name: string;
  parameters: { port: number; vcenters: Array<Parameter> };
  pollers: Array<NamedEntity>;
  type: number;
}

export interface Payload
  extends Omit<
    AdditionalConnectorConfiguration,
    'type' | 'pollers' | 'parameters'
  > {
  parameters: {
    port: number;
    vcenters: Array<{
      name: string;
      password: string | null;
      url: string;
      username: string | null;
    }>;
  };
  pollers: Array<number>;
  type: string;
}

// to be removed

type Icon = NamedEntity & { url: string };

export interface HostGroupListItem extends NamedEntity {
  alias: string | null;
  enabledHostsCount: number;
  disabledHostsCount: number;
  isActivated: boolean;
  icon: null | Icon;
}

export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};
export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};

export interface HostGroupItem extends NamedEntity {
  alias: string | null;
  geoCoords: string | null;
  comment: string | null;
  isActivated: boolean;
  hosts: Array<NamedEntity>;
  resourceAccessRules?: Array<NamedEntity>;
  icon?: null | (NamedEntity & { url: string });
}
