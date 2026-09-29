import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  getDuplicateHostEndpoint,
  getHostEndpoint,
  getHostGroupsEndpoint,
  getHostTemplatesEndpoint,
  getPollersEndpoint,
  hostsBaseEndpoint,
  hostsListDecoder,
  hostsListEndpoint,
  namedEntitiesListDecoder
} from './api';
import {
  labelHostGroup,
  labelHostTemplate,
  labelMonitoringServer,
  labelName,
  labelStatus
} from './translatedLabels';

interface UseHostsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const api: APIType = {
  // This endpoint takes `activate`, not the `is_activated` of the older
  // migrated listings.
  activationField: 'activate',
  apiFormat: 'JSON-LD',
  baseEndpoint: hostsBaseEndpoint,
  decoders: { getAll: hostsListDecoder },
  // Every write names one host, so a selection becomes one request per row.
  endpoints: {
    deleteOne: getHostEndpoint,
    disable: getHostEndpoint,
    duplicate: getDuplicateHostEndpoint,
    enable: getHostEndpoint,
    getAll: hostsListEndpoint
  },
  // The duplicate route takes no body, so there is no copy count to ask for.
  isSingleDuplicate: true,
  methods: {
    disable: Method.PATCH,
    enable: Method.PATCH
  },
  writeBaseEndpoint: hostsBaseEndpoint
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
        baseEndpoint: hostsBaseEndpoint,
        decoder: namedEntitiesListDecoder,
        fieldName: 'poller_id',
        fieldType: FieldType.SingleConnectedAutocomplete,
        getEndpoint: getPollersEndpoint,
        name: t(labelMonitoringServer)
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
