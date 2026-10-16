import { type ListingModel, useFetchQuery } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { find } from 'ramda';
import { useEffect, useMemo, useState } from 'react';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import type { FormPoller, NamedEntity } from '../models';
import { formPollersListDecoder } from './decoders';
import { getFormPollersEndpoint, hostsBaseEndpoint } from './endpoints';

// The form's own selector, so the default is one the poller field offers.
const useDefaultPoller = ({
  enabled
}: {
  enabled: boolean;
}): NamedEntity | null => {
  const formState = useAtomValue(formStateAtom);

  const { data } = useFetchQuery<ListingModel<FormPoller>>({
    baseEndpoint: hostsBaseEndpoint,
    decoder: formPollersListDecoder,
    getEndpoint: getFormPollersEndpoint,
    getQueryKey: () => ['host-form-default-poller'],
    queryOptions: { enabled, suspense: false }
  });

  const fetchedDefault = useMemo(() => {
    const defaultPoller = find(
      ({ isDefault }) => isDefault,
      data?.result ?? []
    );

    return defaultPoller
      ? { id: defaultPoller.id, name: defaultPoller.name }
      : null;
  }, [data]);

  // New defaults reinitialise the form, so one arriving while a host is being
  // created would wipe what was typed: that form goes without it.
  const isCreating = formState.isOpen && formState.mode === 'add';
  const [defaultPoller, setDefaultPoller] = useState(fetchedDefault);

  useEffect(() => {
    if (!isCreating) {
      setDefaultPoller(fetchedDefault);
    }
  }, [fetchedDefault, isCreating]);

  return defaultPoller;
};

export default useDefaultPoller;
