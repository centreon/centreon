import { useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router';

import { pageAtom } from '../ConfigurationBase/Listing/atoms';
import { filtersAtom } from './atoms';
import { filtersInitialValues, hostNameSearchParameter } from './utils';

// A link to one host's services (the Hosts row action) carries the host name
// in the URL. It replaces the stored filters, so the listing shows that host's
// services only, then leaves the URL: a later reload keeps whatever filters
// the user set since.
//
// The effect runs after the one of ConfigurationBase (a child), which resets
// the filters when none are stored, so it is not overwritten.
export const useHostNameFilterFromUrl = (): void => {
  const [searchParams, setSearchParams] = useSearchParams();
  const setFilters = useSetAtom(filtersAtom);
  // Shared by every listing: the one left on the Hosts page must not apply.
  const setPage = useSetAtom(pageAtom);

  const hostName = searchParams.get(hostNameSearchParameter);

  useEffect(() => {
    if (isNil(hostName)) {
      return;
    }

    setFilters({ ...filtersInitialValues, host_name: hostName });
    setPage(undefined);

    setSearchParams(
      (currentSearchParams) => {
        currentSearchParams.delete(hostNameSearchParameter);

        return currentSearchParams;
      },
      { replace: true }
    );
  }, [hostName, setFilters, setPage, setSearchParams]);
};
