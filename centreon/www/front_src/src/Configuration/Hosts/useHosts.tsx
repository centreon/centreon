import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  bulkDeleteHostsEndpoint,
  bulkDuplicateHostsEndpoint,
  getHostEndpoint,
  getHostGroupsEndpoint,
  getHostTemplatesEndpoint,
  hostsBaseEndpoint,
  hostsListDecoder,
  hostsListEndpoint,
  namedEntitiesListDecoder
} from './api';
import {
  labelHostGroup,
  labelHostTemplate,
  labelName,
  labelStatus
} from './translatedLabels';

interface UseHostsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const api: APIType = {
  apiFormat: 'JSON-LD',
  baseEndpoint: hostsBaseEndpoint,
  decoders: { getAll: hostsListDecoder },
  endpoints: {
    delete: bulkDeleteHostsEndpoint,
    deleteOne: getHostEndpoint,
    disable: getHostEndpoint,
    duplicate: bulkDuplicateHostsEndpoint,
    enable: getHostEndpoint,
    getAll: hostsListEndpoint
  },
  // Sends `{ ids }` rather than `{ ids, nb_duplicates }`.
  isSingleDuplicate: true,
  methods: {
    disable: Method.PATCH,
    enable: Method.PATCH
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
      },
      {
        // `fieldName` doubles as the query parameter.
        baseEndpoint: hostsBaseEndpoint,
        decoder: namedEntitiesListDecoder,
        fieldName: 'group_id',
        fieldType: FieldType.SingleConnectedAutocomplete,
        getEndpoint: getHostGroupsEndpoint,
        name: t(labelHostGroup)
      },
      {
        baseEndpoint: hostsBaseEndpoint,
        decoder: namedEntitiesListDecoder,
        fieldName: 'template_id',
        fieldType: FieldType.SingleConnectedAutocomplete,
        getEndpoint: getHostTemplatesEndpoint,
        name: t(labelHostTemplate)
      },
      {
        // This endpoint spells it `activated`, not `is_activated`.
        fieldName: 'activated',
        fieldType: FieldType.Status,
        name: t(labelStatus)
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
