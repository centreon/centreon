import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { type APIType, FieldType, type FilterConfiguration } from '../models';
import {
  hostTemplatesBaseEndpoint,
  hostTemplatesListDecoder,
  hostTemplatesListEndpoint
} from './api';
import { labelLockedElements, labelName } from './translatedLabels';

interface UseHostTemplatesState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

// Listing only: the write endpoints land with the form and actions sub-tasks.
// No `activationField`: a host template cannot be enabled or disabled.
const api: APIType = {
  apiFormat: 'JSON-LD',
  baseEndpoint: hostTemplatesBaseEndpoint,
  decoders: { getAll: hostTemplatesListDecoder },
  endpoints: {
    getAll: hostTemplatesListEndpoint
  }
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
        // Checked sends `is_locked=true`; unchecked sends nothing.
        fieldName: 'is_locked',
        fieldType: FieldType.Checkbox,
        name: t(labelLockedElements)
      }
    ],
    [t]
  );

  return { api, filtersConfiguration };
};

export default useHostTemplates;
