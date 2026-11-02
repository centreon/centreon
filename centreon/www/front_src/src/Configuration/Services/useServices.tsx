import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  getDuplicateServiceEndpoint,
  getServiceEndpoint,
  getServiceTemplatesEndpoint,
  namedEntitiesListDecoder,
  servicesBaseEndpoint,
  servicesListDecoder,
  servicesListEndpoint
} from './api';
import {
  labelHostName,
  labelIncludeDisabledHosts,
  labelName,
  labelServiceTemplate,
  labelStatus
} from './translatedLabels';

interface UseServicesState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const api: APIType = {
  activationField: 'activated',
  apiFormat: 'JSON-LD',
  baseEndpoint: servicesBaseEndpoint,
  decoders: { getAll: servicesListDecoder },
  // Every write names one service, so a selection becomes one request per row.
  endpoints: {
    deleteOne: getServiceEndpoint,
    disable: getServiceEndpoint,
    duplicate: getDuplicateServiceEndpoint,
    enable: getServiceEndpoint,
    getAll: servicesListEndpoint
  },
  // The duplicate route takes no body, so there is no copy count to ask for.
  isSingleDuplicate: true,
  methods: {
    disable: Method.PATCH,
    enable: Method.PATCH
  },
  writeBaseEndpoint: servicesBaseEndpoint
};

const useServices = (): UseServicesState => {
  const { t } = useTranslation();

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      },
      {
        // The host name only, not its alias or address as the legacy page did.
        fieldName: 'host.name',
        fieldType: FieldType.Text,
        name: t(labelHostName)
      },
      {
        // `fieldName` doubles as the query parameter.
        baseEndpoint: servicesBaseEndpoint,
        decoder: namedEntitiesListDecoder,
        fieldName: 'template_id',
        fieldType: FieldType.SingleConnectedAutocomplete,
        getEndpoint: getServiceTemplatesEndpoint,
        name: t(labelServiceTemplate)
      },
      {
        fieldName: 'activated',
        fieldType: FieldType.Status,
        name: t(labelStatus)
      },
      {
        // Off by default: the services of a disabled host are left out, as
        // the legacy page did.
        fieldName: 'include_disabled_hosts',
        fieldType: FieldType.Checkbox,
        name: t(labelIncludeDisabledHosts)
      }
    ],
    [t]
  );

  return { api, filtersConfiguration };
};

export default useServices;
