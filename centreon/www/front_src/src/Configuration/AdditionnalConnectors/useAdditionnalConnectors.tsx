import { equals, omit, pluck } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { APIType, FieldType, FilterConfiguration } from '../models';
import {
  additionalConnectorDecoder,
  additionalConnectorsEndpoint,
  additionalConnectorsListDecoder,
  getAdditionalConnectorEndpoint,
  getPollersEndpoint
} from './api';
import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Payload
} from './models';
import { labelName, labelPoller, labelType } from './translatedLabels';
import { findConnectorTypeById, maskedPassword } from './utils';

interface UseAdditionnalConnectorsState {
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
        id: vcenter?.id || null,
        name: vcenter[ParameterKeys.name],
        password: equals(vcenter[ParameterKeys.password], maskedPassword)
          ? null
          : vcenter[ParameterKeys.password],
        url: vcenter[ParameterKeys.url],
        username: vcenter[ParameterKeys.username]
      }))
    },
    pollers: pluck('id', formData.pollers),
    type: findConnectorTypeById(formData.type)?.name as string
  };
};

const useAdditionnalConnectors = (): UseAdditionnalConnectorsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      adapter: adaptFormToApiPayload,
      decoders: {
        getAll: additionalConnectorsListDecoder,
        getOne: additionalConnectorDecoder
      },
      endpoints: {
        create: additionalConnectorsEndpoint,
        deleteOne: getAdditionalConnectorEndpoint,
        getAll: additionalConnectorsEndpoint,
        getOne: getAdditionalConnectorEndpoint,
        update: getAdditionalConnectorEndpoint
      }
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      },
      {
        fieldName: 'type',
        fieldType: FieldType.MultiAutocomplete,
        name: t(labelType),
        options: [{ id: 'vmware_v6', name: 'VMWare 6/7' }]
      },
      {
        fieldName: 'poller.id',
        fieldType: FieldType.MultiConnectedAutocomplete,
        getEndpoint: getPollersEndpoint,
        name: t(labelPoller)
      }
    ],
    []
  );

  return {
    api,
    filtersConfiguration
  };
};

export default useAdditionnalConnectors;
