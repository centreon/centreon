import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import { getPayload } from '../ResourceForm/blocks';
import {
  getDuplicateHostTemplateEndpoint,
  getHostTemplateDecoder,
  getHostTemplateEndpoint,
  hostTemplatesBaseEndpoint,
  hostTemplatesListDecoder,
  hostTemplatesListEndpoint
} from './api';
import { blocks, type FormValues, type PlatformContext } from './Form/sections';
import { labelLockedElements, labelName } from './translatedLabels';

interface UseHostTemplatesState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const getAdapter = (context: PlatformContext) => (data: unknown) =>
  getPayload(blocks, data as FormValues, context);

// No `activationField`: a host template cannot be enabled or disabled.
const getApi = (context: PlatformContext): APIType => ({
  adapter: getAdapter(context),
  apiFormat: 'JSON-LD',
  baseEndpoint: hostTemplatesBaseEndpoint,
  decoders: {
    getAll: hostTemplatesListDecoder,
    getOne: getHostTemplateDecoder(context)
  },
  // Every write names one template, so a selection becomes one request per row.
  endpoints: {
    create: hostTemplatesListEndpoint,
    deleteOne: getHostTemplateEndpoint,
    duplicate: getDuplicateHostTemplateEndpoint,
    getAll: hostTemplatesListEndpoint,
    getOne: getHostTemplateEndpoint,
    update: getHostTemplateEndpoint
  },
  isDuplicateWithName: true,
  // Each call makes one copy: there is no copy count to ask for.
  isSingleDuplicate: true,
  // A partial update: a key the form leaves out keeps its stored value.
  methods: { update: Method.PATCH },
  writeBaseEndpoint: hostTemplatesBaseEndpoint
});

const useHostTemplates = ({
  isAdditiveInheritanceEnabled,
  isCloudPlatform
}: PlatformContext): UseHostTemplatesState => {
  const { t } = useTranslation();

  const api = useMemo(
    () => getApi({ isAdditiveInheritanceEnabled, isCloudPlatform }),
    [isAdditiveInheritanceEnabled, isCloudPlatform]
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        // Sent as `name[lk]`, which the endpoint matches on name or alias.
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      },
      {
        // Checked sends `include_locked=true` (locked included); unchecked, unlocked only.
        fieldName: 'include_locked',
        fieldType: FieldType.Checkbox,
        name: t(labelLockedElements)
      }
    ],
    [t]
  );

  return { api, filtersConfiguration };
};

export default useHostTemplates;
