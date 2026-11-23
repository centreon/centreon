import { useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router';

import { pageAtom } from '../ConfigurationBase/Listing/atoms';
import { filtersAtom } from './atoms';
import { filtersInitialValues, hostNameSearchParameter } from './utils';

// Runs after the effect of ConfigurationBase (a child), which resets the
// filters when none are stored, so the host filter is not overwritten.
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
