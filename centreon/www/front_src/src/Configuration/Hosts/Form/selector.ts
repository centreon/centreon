import type { InputProps } from '@centreon/ui';

import { hostsBaseEndpoint } from '../api/endpoints';
import { namedEntitiesListDecoder } from '../api/namedEntityDecoders';

type ConnectedAutocomplete = NonNullable<InputProps['connectedAutocomplete']>;

// What every selector of the form shares: an endpoint of API Platform that
// answers in Hydra.
export const buildSelector = ({
  endpoint,
  queryKey,
  ...rest
}: Partial<ConnectedAutocomplete> &
  Required<
    Pick<ConnectedAutocomplete, 'endpoint' | 'queryKey'>
  >): ConnectedAutocomplete => ({
  additionalConditionParameters: [],
  baseEndpoint: hostsBaseEndpoint,
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
