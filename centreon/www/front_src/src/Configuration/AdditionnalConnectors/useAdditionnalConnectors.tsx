import { omit, pluck } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import {
  additionalConnectorDecoder,
  additionalConnectorsEndpoint,
  additionalConnectorsListDecoder,
  getAdditionalConnectorEndpoint,
  getPollersEndpoint
} from './api';

import { APIType, FieldType, FilterConfiguration } from '../models';
import {
  AdditionalConnectorConfiguration,
  ParameterKeys,
  Payload
} from './models';
import { findConnectorTypeById, splitURL } from './utils';

import { labelName, labelPollers, labelTypes } from './translatedLabels';

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

const useAdditionnalConnectors = (): UseAdditionnalConnectorsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: additionalConnectorsEndpoint,
        getOne: getAdditionalConnectorEndpoint,
        deleteOne: getAdditionalConnectorEndpoint,
        create: additionalConnectorsEndpoint,
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
        name: t(labelTypes),
        fieldType: FieldType.MultiAutocomplete,
        options: [{ id: 'vmware_v6', name: 'VMWare 6/7' }],
        fieldName: 'type'
      },
      {
        name: t(labelPollers),
        fieldType: FieldType.MultiConnectedAutocomplete,
        getEndpoint: getPollersEndpoint,
        fieldName: 'poller.id'
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
