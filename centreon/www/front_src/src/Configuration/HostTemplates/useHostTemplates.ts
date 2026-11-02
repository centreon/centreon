import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  getDuplicateHostTemplateEndpoint,
  getHostTemplateEndpoint,
  hostTemplatesBaseEndpoint,
  hostTemplatesListDecoder,
  hostTemplatesListEndpoint
} from './api';
import { labelLockedElements, labelName } from './translatedLabels';

interface UseHostTemplatesState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

// No `activationField`: a host template cannot be enabled or disabled.
const api: APIType = {
  apiFormat: 'JSON-LD',
  baseEndpoint: hostTemplatesBaseEndpoint,
  decoders: { getAll: hostTemplatesListDecoder },
  // Every write names one template, so a selection becomes one request per row.
  endpoints: {
    deleteOne: getHostTemplateEndpoint,
    duplicate: getDuplicateHostTemplateEndpoint,
    getAll: hostTemplatesListEndpoint
  },
  // Each call makes one copy: there is no copy count to ask for.
  isSingleDuplicate: true,
  writeBaseEndpoint: hostTemplatesBaseEndpoint
};

const useHostTemplates = (): UseHostTemplatesState => {
  const { t } = useTranslation();

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
