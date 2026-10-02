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
  hostDecoder,
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

// API Platform takes snake_case, and ids where the form holds the options the
// autocompletes selected.
const adaptFormToApiPayload = (data: unknown) => {
  const { name, address, poller, groups } = data as {
    address: string;
    groups: Array<{ id: number }> | null;
    name: string;
    poller: { id: number } | null;
  };

  return {
    // Trimmed as the schema validates them: yup casts before checking the
    // length, so an untrimmed value passes `max` here and fails it server side.
    address: address?.trim(),
    host_group_ids: (groups ?? []).map(({ id }) => id),
    name: name?.trim(),
    poller_id: poller?.id
  };
};

const api: APIType = {
  // This endpoint takes `activate`, not the `is_activated` of the older
  // migrated listings.
  activationField: 'activate',
  adapter: adaptFormToApiPayload,
  apiFormat: 'JSON-LD',
  baseEndpoint: hostsBaseEndpoint,
  decoders: { getAll: hostsListDecoder, getOne: hostDecoder },
  // Every write names one host, so a selection becomes one request per row.
  endpoints: {
    create: hostsListEndpoint,
    deleteOne: getHostEndpoint,
    disable: getHostEndpoint,
    duplicate: getDuplicateHostEndpoint,
    enable: getHostEndpoint,
    getAll: hostsListEndpoint,
    getOne: getHostEndpoint,
    update: getHostEndpoint
  },
  // The duplicate route takes no body, so there is no copy count to ask for.
  isSingleDuplicate: true,
  methods: {
    disable: Method.PATCH,
    enable: Method.PATCH,
    // The same operation enable and disable use. `PatchHostInput` accepts only
    // `activated` today, so editing a host answers 422 until it carries the
    // rest of the form; declaring it is what makes that visible rather than
    // leaving the save silently doing nothing.
    update: Method.PATCH
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
