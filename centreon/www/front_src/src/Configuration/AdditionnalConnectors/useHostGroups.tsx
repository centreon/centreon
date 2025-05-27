import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { hostGroupDecoder } from './api';

import { APIType, FieldType, FilterConfiguration } from '../models';

import { additionalConnectorsListDecoder } from './api/decoders';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from './api/endpoints';
import { labelAlias, labelName, labelStatus } from './translatedLabels';

interface UseHostGroupsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const adaptFormToApiPayload = () => {
  const payload = {};

  return payload;
};

const useHostGroups = (): UseHostGroupsState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: additionalConnectorsEndpoint,
        getOne: getAdditionalConnectorEndpoint,
        deleteOne: getAdditionalConnectorEndpoint,
        delete: additionalConnectorsEndpoint,
        duplicate: additionalConnectorsEndpoint,
        disable: additionalConnectorsEndpoint,
        enable: additionalConnectorsEndpoint,
        create: additionalConnectorsEndpoint,
        update: getAdditionalConnectorEndpoint
      },
      decoders: {
        getAll: additionalConnectorsListDecoder,
        getOne: hostGroupDecoder
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
        name: t(labelAlias),
        fieldName: 'alias',
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

export default useHostGroups;
