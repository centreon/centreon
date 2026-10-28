import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  getDuplicateServiceEndpoint,
  getServiceDecoder,
  getServiceEndpoint,
  getServiceTemplatesEndpoint,
  namedEntitiesListDecoder,
  servicesBaseEndpoint,
  servicesListDecoder,
  servicesListEndpoint
} from './api';
import { getAdapter } from './Form';
import type { PlatformContext } from './Form/sections';
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

// The form routes are placeholders until the backend exposes them: the read
// and the full update on the service, the create on the collection.
const getApi = (context: PlatformContext): APIType => ({
  activationField: 'activated',
  adapter: getAdapter(context),
  apiFormat: 'JSON-LD',
  baseEndpoint: servicesBaseEndpoint,
  decoders: { getAll: servicesListDecoder, getOne: getServiceDecoder(context) },
  // Every write names one service, so a selection becomes one request per row.
  endpoints: {
    create: servicesListEndpoint,
    deleteOne: getServiceEndpoint,
    disable: getServiceEndpoint,
    duplicate: getDuplicateServiceEndpoint,
    enable: getServiceEndpoint,
    getAll: servicesListEndpoint,
    getOne: getServiceEndpoint,
    update: getServiceEndpoint
  },
  // The duplicate route takes no body, so there is no copy count to ask for.
  isSingleDuplicate: true,
  methods: {
    disable: Method.PATCH,
    enable: Method.PATCH,
    // A mass change sends only what the user changed, so it patches each
    // service rather than replacing it.
    massUpdate: Method.PATCH,
    // The form sends every field, so it replaces the service rather than
    // patching it; enable and disable stay partial updates.
    update: Method.PUT
  },
  writeBaseEndpoint: servicesBaseEndpoint
});

const useServices = ({
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: PlatformContext): UseServicesState => {
  const { t } = useTranslation();

  const api = useMemo(
    () => getApi({ isAdditiveInheritanceEnabled, isCloudPlatform }),
    [isAdditiveInheritanceEnabled, isCloudPlatform]
  );

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
