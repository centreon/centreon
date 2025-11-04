import { omit, pluck } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  additionalConnectorDecoder,
  additionalConnectorsListDecoder,
  commandsEndpoint,
  getAdditionalConnectorEndpoint
} from './api';

import { APIType, FieldType, FilterConfiguration } from '../models';
import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Payload
} from './models';
import { findConnectorTypeById, splitURL } from './utils';

import { labelName, labelStatus } from './translatedLabels';

interface UseCommandsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

export const adaptFormToApiPayload = (
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

const useCommands = (): UseCommandsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: commandsEndpoint,
        getOne: getAdditionalConnectorEndpoint,
        deleteOne: getAdditionalConnectorEndpoint,
        create: commandsEndpoint,
        update: getAdditionalConnectorEndpoint
      },
      decoders: {
        getAll: additionalConnectorsListDecoder,
        getOne: additionalConnectorDecoder
      },
      adapter: adaptFormToApiPayload
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        name: t(labelName),
        fieldName: 'name',
        fieldType: FieldType.Text
      },
      {
        name: t(labelStatus),
        fieldType: FieldType.Status
      }
    ],
    []
  );

  return {
    api,
    filtersConfiguration
  };
};

export default useCommands;
