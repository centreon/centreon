import { omit, pluck } from 'ramda';

import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Payload
} from '../Modal/models';
import { findConnectorTypeById } from '../utils';

export const adaptFormDataToApiPayload = (
  formData: AdditionalConnectorConfiguration
): Payload => {
  return {
    ...omit(['id'], formData),
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
