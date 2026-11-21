import { useFetchQuery } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { number, ObjectSchema, object, string } from 'yup';

import { baseEndpoint } from '../../../api/endpoint';
import useGetDetails from '../../ConfigurationBase/api/useGetOne';
import { formStateAtom } from '../../ConfigurationBase/atoms';
import { aiProvidersEndpoint, aiProvidersListDecoder } from '../api';
import { AiProviderType } from '../models';
import {
  labelApiTokenRequiredForNewEndpoint,
  labelCurrencyRequired,
  labelInvalidCurrency,
  labelInvalidUrl,
  labelNameAlreadyExists,
  labelPricingRequiredForLimit,
  labelRequired
} from '../translatedLabels';
import { isSameEndpoint } from '../utils';

const billingFields = [
  'priceDefaultInput',
  'priceDefaultOutput',
  'priceFastInput',
  'priceFastOutput',
  'limitDaily',
  'limitMonthly'
];

// a cleared number field comes through as an empty string: treat it as null
const optionalNumber = () =>
  number()
    .nullable()
    .transform((value, original) => (original === '' ? null : value))
    .min(0);

interface PendingLookup {
  resolve: (available: boolean) => void;
  timer: ReturnType<typeof setTimeout>;
}

const lookupDebounceMs = 500;

export const useValidationSchema = (): {
  validationSchema: ObjectSchema<object>;
} => {
  const { t } = useTranslation();

  const { mode, id } = useAtomValue(formStateAtom);
  const isEditing = equals(mode, 'edit');

  // the provider being edited, already loaded (and cached) by the form
  const { data: storedProvider } = useGetDetails({
    id: isEditing ? id : null
  });
  const storedEndpointUrl = (
    storedProvider as { endpointUrl?: string } | undefined
  )?.endpointUrl;

  // id of the existing provider using a given name (null when unused),
  // cached so that re-validations do not query the API again
  const knownNamesRef = useRef<Record<string, number | null>>({});
  const pendingLookupRef = useRef<PendingLookup | null>(null);

  // the providers whose name contains the one being checked, fetched on
  // demand: the name sits in a ref so the endpoint and the key read it when
  // the lookup runs
  const lookedUpNameRef = useRef('');
  const { fetchQuery: fetchProvidersNamedLike } = useFetchQuery({
    decoder: aiProvidersListDecoder,
    getEndpoint: () =>
      `${baseEndpoint}${aiProvidersEndpoint}?name[lk]=${encodeURIComponent(lookedUpNameRef.current)}`,
    getQueryKey: () => ['aiProviderNameLookup', lookedUpNameRef.current],
    // a failed lookup does not block the form: the API rejects duplicate
    // names anyway
    httpCodesBypassErrorSnackbar: [400, 401, 403, 404, 500, 502, 503],
    queryOptions: { enabled: false, suspense: false }
  });

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

        lookedUpNameRef.current = name;
        const response = await fetchProvidersNamedLike();

        if ('isError' in response) {
          // do not block the form on a failed lookup: the API rejects
          // duplicate names anyway
          resolve(true);

          return;
        }

        const existing = response.result.find((provider) =>
          equals(provider.name, name)
        );
        knownNamesRef.current[name] = existing?.id ?? null;

        resolve(isKnownNameAvailable(name));
      }, lookupDebounceMs);

      pendingLookupRef.current = { resolve, timer };
    });
  };

  const validationSchema = object({
    // when editing, an empty token keeps the stored one, which is only sent
    // to the endpoint it was configured for
    apiToken: isEditing
      ? string()
          .nullable()
          .when('endpointUrl', ([endpointUrl], schema) =>
            // the API enforces it anyway: do not block before the details load
            isNil(storedEndpointUrl) ||
            isSameEndpoint(endpointUrl, storedEndpointUrl)
              ? schema
              : schema.required(t(labelApiTokenRequiredForNewEndpoint))
          )
      : string().required(t(labelRequired)),
    // a price or a limit requires a currency
    currency: string()
      .nullable()
      .matches(/^[A-Za-z]{3}$/, {
        excludeEmptyString: true,
        message: t(labelInvalidCurrency)
      })
      .when(billingFields, (values, schema) =>
        (values as Array<number | null | undefined>).some(
          (value) => !isNil(value) && (value as unknown) !== ''
        )
          ? schema.required(t(labelCurrencyRequired))
          : schema
      ),
    defaultModel: string().required(t(labelRequired)),
    // the endpoint is provided for OpenAI-compatible and Bedrock providers
    // (Bedrock derives it from the region); curated types enforce it API-side
    endpointUrl: string().when('type', ([type], schema) =>
      equals(type, AiProviderType.OpenAiCompatible) ||
      equals(type, AiProviderType.Bedrock)
        ? schema
            .matches(/^https?:\/\/.+/, t(labelInvalidUrl))
            .required(t(labelRequired))
        : schema.nullable()
    ),
    // a limit needs the default model prices so a cost can be computed
    limitDaily: optionalNumber().when(
      ['priceDefaultInput', 'priceDefaultOutput'],
      ([input, output], schema) =>
        isNil(input) || isNil(output)
          ? schema.test(
              'requires-pricing',
              t(labelPricingRequiredForLimit),
              (value) => isNil(value)
            )
          : schema
    ),
    limitMonthly: optionalNumber().when(
      ['priceDefaultInput', 'priceDefaultOutput'],
      ([input, output], schema) =>
        isNil(input) || isNil(output)
          ? schema.test(
              'requires-pricing',
              t(labelPricingRequiredForLimit),
              (value) => isNil(value)
            )
          : schema
    ),
    // checked and sent trimmed (see adaptFormToApiPayload)
    name: string()
      .trim()
      .required(t(labelRequired))
      .test(
        'unique-name',
        t(labelNameAlreadyExists),
        (value) => !value || isNameAvailable(value)
      ),
    priceDefaultInput: optionalNumber(),
    priceDefaultOutput: optionalNumber(),
    priceFastInput: optionalNumber(),
    priceFastOutput: optionalNumber(),
    type: string().required(t(labelRequired))
  });

  return { validationSchema };
};
