import { Method } from '@centreon/ui';

import { mergeAll } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { getHostDecoder } from '../Hosts/api';
import {
  type FormValues,
  getAvailableSections,
  type PlatformContext
} from '../Hosts/Form/sections';
import { labelName } from '../Hosts/translatedLabels';
import { type APIType, FieldType, type FilterConfiguration } from '../models';
import { hostTemplatesListDecoder } from './api/decoders';
import {
  getDuplicateHostTemplateEndpoint,
  getHostTemplateEndpoint,
  hostTemplatesBaseEndpoint,
  hostTemplatesListEndpoint
} from './api/endpoints';
import { labelLockedElements } from './translatedLabels';

interface UseHostTemplatesState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

type Context = Omit<PlatformContext, 'isTemplate'>;

const getAdapter = (context: PlatformContext) => (data: unknown) =>
  mergeAll(
    getAvailableSections(context).map(({ section }) =>
      section.toPayload(data as FormValues, context)
    )
  );

// The host's operations, on the template resource. A template has no
// activation, so there is no enable nor disable.
const getApi = (context: Context): APIType => {
  const templateContext = { ...context, isTemplate: true };

  return {
    adapter: getAdapter(templateContext),
    apiFormat: 'JSON-LD',
    baseEndpoint: hostTemplatesBaseEndpoint,
    decoders: {
      getAll: hostTemplatesListDecoder,
      getOne: getHostDecoder(templateContext)
    },
    // Every write names one template, so a selection becomes one request per
    // row.
    endpoints: {
      create: hostTemplatesListEndpoint,
      deleteOne: getHostTemplateEndpoint,
      duplicate: getDuplicateHostTemplateEndpoint,
      getAll: hostTemplatesListEndpoint,
      getOne: getHostTemplateEndpoint,
      update: getHostTemplateEndpoint
    },
    // As for hosts, the duplicate route takes no body: one copy per request.
    isSingleDuplicate: true,
    methods: { update: Method.PATCH },
    writeBaseEndpoint: hostTemplatesBaseEndpoint
  };
};

const useHostTemplates = ({
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: Context): UseHostTemplatesState => {
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
        fieldName: 'isLockedShown',
        fieldType: FieldType.Checkbox,
        // Unchecked narrows the listing: the same endpoint feeds the hosts
        // template filter, which must keep offering locked templates.
        getQueryParameters: (isLockedShown) =>
          isLockedShown ? [] : [{ name: 'is_locked', value: false }],
        name: t(labelLockedElements)
      }
    ],
    [t]
  );

  return { api, filtersConfiguration };
};

export default useHostTemplates;
