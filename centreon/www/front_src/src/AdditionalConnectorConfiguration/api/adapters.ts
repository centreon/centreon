import { omit, pluck } from 'ramda';

import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Payload
} from '../Modal/models';
import { findConnectorTypeById } from '../utils';

const splitURL = (url) => {
  const includesHTTPPrefix = url.match(/https?:\/\//);

  if (!includesHTTPPrefix) {
    return {
      mainURL: url,
      scheme: null
    };
  }

  return {
    mainURL: url.split('://')?.[1],
    scheme: url.split('://')?.[0]
  };
};

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
        url: splitURL(vcenter[ParameterKeys.url]).mainURL,
        username: vcenter[ParameterKeys.username],
        scheme: splitURL(vcenter[ParameterKeys.url]).scheme
      }))
    },
    pollers: pluck('id', formData.pollers),
    type: findConnectorTypeById(formData.type)?.name as string
  };
};
