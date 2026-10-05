import { type ListingModel, useFetchQuery } from '@centreon/ui';

import { find } from 'ramda';
import { useMemo } from 'react';

import type { FormPoller, NamedEntity } from '../models';
import { formPollersListDecoder } from './decoders';
import { getFormPollersEndpoint, hostsBaseEndpoint } from './endpoints';

// The form's own selector, so the default is one the poller field offers.
const useDefaultPoller = ({
  enabled
}: {
  enabled: boolean;
}): NamedEntity | null => {
  const { data } = useFetchQuery<ListingModel<FormPoller>>({
    baseEndpoint: hostsBaseEndpoint,
    decoder: formPollersListDecoder,
    getEndpoint: getFormPollersEndpoint,
    getQueryKey: () => ['host-form-default-poller'],
    queryOptions: { enabled, suspense: false }
  });

  return useMemo(() => {
    const defaultPoller = find(
      ({ isDefault }) => isDefault,
      data?.result ?? []
    );

    return defaultPoller
      ? { id: defaultPoller.id, name: defaultPoller.name }
      : null;
  }, [data]);
};

export default useDefaultPoller;
