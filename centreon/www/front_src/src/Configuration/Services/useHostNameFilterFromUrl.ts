import { useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router';

import { filtersAtom } from './atoms';
import { filtersInitialValues, hostNameSearchParameter } from './utils';

// A link to one host's services (the Hosts row action) carries the host name
// in the URL. It replaces the stored filters, so the listing shows that host's
// services and nothing else, then leaves the URL: a later reload keeps
// whatever filters the user set since.
//
// This runs after the effect of ConfigurationBase (a child), which resets the
// filters when none are stored, so it is not overwritten.
const useHostNameFilterFromUrl = (): void => {
  const [searchParams, setSearchParams] = useSearchParams();
  const setFilters = useSetAtom(filtersAtom);

  const hostName = searchParams.get(hostNameSearchParameter);

  useEffect(() => {
    if (isNil(hostName)) {
      return;
    }

    setFilters({ ...filtersInitialValues, 'host.name': hostName });

    setSearchParams(
      (currentSearchParams) => {
        currentSearchParams.delete(hostNameSearchParameter);

        return currentSearchParams;
      },
      { replace: true }
    );
  }, [hostName, setFilters, setSearchParams]);
};

export default useHostNameFilterFromUrl;
