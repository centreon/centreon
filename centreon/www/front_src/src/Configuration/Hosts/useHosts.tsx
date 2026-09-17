import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import { hostsBaseEndpoint, hostsListDecoder, hostsListEndpoint } from './api';
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
   * `baseEndpoint` targets API Platform rather than the legacy route still
   * answering at `./api/latest` — see api/endpoints.ts. `JSON-LD` goes with it:
   * it selects the Hydra envelope in the decoder and the `page`/`itemsPerPage`
   * and `name[lk]` query parameters that `HostResource` declares.
   */
  const api: APIType = useMemo(
    () => ({
      apiFormat: 'JSON-LD',
      baseEndpoint: hostsBaseEndpoint,
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
