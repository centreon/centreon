import { NamedEntity } from '../Listing/models';

export enum ParameterKeys {
  name = 'Vcenter name',
  password = 'Password',
  url = 'Url',
  username = 'Username'
}

export interface Parameter {
  [ParameterKeys.name]: string;
  [ParameterKeys.url]: string;
  [ParameterKeys.username]: string | null;
  [ParameterKeys.password]: string | null;
}

export interface AdditionalConnectorConfiguration {
  description: null | string;
  name: string;
  parameters: { port: number; vcenters: Array<Parameter> };
  pollers: Array<NamedEntity>;
  type: number;
}
