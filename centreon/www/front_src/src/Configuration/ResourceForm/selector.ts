import type { InputProps } from '@centreon/ui';

import { namedEntitiesListDecoder } from './api/decoders';

type ConnectedAutocomplete = NonNullable<InputProps['connectedAutocomplete']>;

// What every selector of the form shares: an endpoint of API Platform that
// answers in Hydra, under the resource's base endpoint.
export const buildSelector = ({
  baseEndpoint,
  endpoint,
  queryKey,
  ...rest
}: Partial<ConnectedAutocomplete> &
  Required<
    Pick<ConnectedAutocomplete, 'baseEndpoint' | 'endpoint' | 'queryKey'>
  >): ConnectedAutocomplete => ({
  additionalConditionParameters: [],
  baseEndpoint,
  customQueryParameters: [],
  decoder: namedEntitiesListDecoder,
  endpoint,
  queryKey,
  useNewAPIFormat: true,
  ...rest
});

// API Platform takes ids where the form holds the options the autocompletes
// selected.
export const toIds = (
  entities: Array<{ id: number }> | null | undefined
): Array<number> => (entities ?? []).map(({ id }) => id);
