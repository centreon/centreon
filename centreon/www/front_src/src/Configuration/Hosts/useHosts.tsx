import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import { hostsBaseEndpoint, hostsListDecoder, hostsListEndpoint } from './api';
import { labelName } from './translatedLabels';

interface UseHostsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const api: APIType = {
  apiFormat: 'JSON-LD',
  baseEndpoint: hostsBaseEndpoint,
  decoders: { getAll: hostsListDecoder },
  endpoints: {
    getAll: hostsListEndpoint
  }
};

const useHosts = (): UseHostsState => {
  const { t } = useTranslation();

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      }
    ],
    [t]
  );

  return {
    api,
    filtersConfiguration
  };
};

export default useHosts;
