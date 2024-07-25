import { pluck } from 'ramda';

import {
  AdditionalConnectorConfiguration,
  ParameterKeys
} from '../Modal/models';
import { findConnectorTypeById } from '../utils';

interface Payload
  extends Omit<
    AdditionalConnectorConfiguration,
    'type' | 'pollers' | 'parameters'
  > {
  parameters: {
    port: number;
    vcenters: Array<{
      name: string;
      password: string;
      url: string;
      username: string;
    }>;
  };
  pollers: Array<number>;
  type: string;
}

export const adaptFormDataToApiPayload = (
  formData: AdditionalConnectorConfiguration
): Payload => {
  return {
    ...formData,
    parameters: {
      ...formData.parameters,
      vcenters: formData.parameters.vcenters.map((vcenter) => ({
        name: vcenter[ParameterKeys.name],
        password: vcenter[ParameterKeys.password],
        url: vcenter[ParameterKeys.url],
        username: vcenter[ParameterKeys.username]
      }))
    },
    pollers: pluck('id', formData.pollers),
    type: findConnectorTypeById(formData.type)?.name as string
  };
};
