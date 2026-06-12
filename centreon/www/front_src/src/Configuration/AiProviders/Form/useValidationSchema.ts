import axios from 'axios';
import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { ObjectSchema, object, string } from 'yup';

import { baseEndpoint } from '../../../api/endpoint';
import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { aiProvidersEndpoint } from '../api';
import { AiProviderType } from '../models';
import {
  labelInvalidUrl,
  labelNameAlreadyExists,
  labelRequired
} from '../translatedLabels';

interface ListingResponse {
  member?: Array<{ id: number; name: string }>;
}

interface PendingLookup {
  resolve: (available: boolean) => void;
  timer: ReturnType<typeof setTimeout>;
}

const lookupDebounceMs = 500;

export const useValidationSchema = (): ObjectSchema<object> => {
  const { t } = useTranslation();

  const { mode, id } = useAtomValue(modalStateAtom);
  const isEditing = equals(mode, 'edit');

  // id of the existing provider using a given name (null when unused),
  // cached so that re-validations do not query the API again
  const knownNamesRef = useRef<Record<string, number | null>>({});
  const pendingLookupRef = useRef<PendingLookup | null>(null);

  const isKnownNameAvailable = (name: string): boolean => {
    const existingId = knownNamesRef.current[name];

    return isNil(existingId) || (isEditing && equals(existingId, id));
  };

  const isNameAvailable = (name: string): Promise<boolean> => {
    if (name in knownNamesRef.current) {
      return Promise.resolve(isKnownNameAvailable(name));
    }

    return new Promise((resolve) => {
      // debounce while the user is typing: the validation run of a
      // superseded value passes optimistically, the run of the last
      // value sets the final verdict
      if (pendingLookupRef.current) {
        clearTimeout(pendingLookupRef.current.timer);
        pendingLookupRef.current.resolve(true);
      }

      const timer = setTimeout(async () => {
        pendingLookupRef.current = null;

        try {
          const { data } = await axios.get<ListingResponse>(
            `${baseEndpoint}${aiProvidersEndpoint}?name[lk]=${encodeURIComponent(name)}`
          );
          const existing = (data.member || []).find((provider) =>
            equals(provider.name, name)
          );
          knownNamesRef.current[name] = existing?.id ?? null;

          resolve(isKnownNameAvailable(name));
        } catch (_error) {
          // do not block the form on a failed lookup: the API rejects
          // duplicate names anyway
          resolve(true);
        }
      }, lookupDebounceMs);

      pendingLookupRef.current = { resolve, timer };
    });
  };

  return object({
    apiToken: isEditing
      ? string().nullable()
      : string().required(t(labelRequired)),
    defaultModel: string().required(t(labelRequired)),
    // the endpoint is only exposed for OpenAI-compatible providers:
    // the API enforces the official endpoint of the other types
    endpointUrl: string().when('type', ([type], schema) =>
      equals(type, AiProviderType.OpenAiCompatible)
        ? schema
            .matches(/^https?:\/\/.+/, t(labelInvalidUrl))
            .required(t(labelRequired))
        : schema.nullable()
    ),
    name: string()
      .required(t(labelRequired))
      .test(
        'unique-name',
        t(labelNameAlreadyExists),
        (value) => !value || isNameAvailable(value)
      ),
    type: string().required(t(labelRequired))
  });
};
