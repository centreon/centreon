import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import { hostsListDecoder, hostsListEndpoint } from './api';
import { labelName } from './translatedLabels';

interface UseHostsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const useHosts = (): UseHostsState => {
  const { t } = useTranslation();

  /**
   * Read-only for now. The bulk endpoints (_delete, _duplicate, _enable,
   * _disable) do not exist for hosts yet, so no action endpoints are declared
   * and no actions are enabled on the page.
   *
   * No `apiFormat`: the endpoint currently served at /api/latest is the legacy
   * one, which uses the Standard `{ result, meta }` envelope and `limit`/`page`
   * query parameters. See the decoder for the switch to API Platform.
   */
  const api: APIType = useMemo(
    () => ({
      decoders: { getAll: hostsListDecoder },
      endpoints: {
        getAll: hostsListEndpoint
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
