import { TestQueryProvider } from '@centreon/ui';

import { Provider } from 'jotai';

import useListing from '../../../Listing/useListing';
import Context, { type ResourceContext } from '../../../testUtils/Context';
import useFilter from '../../../testUtils/useFilter';
import EditFiltersPanel from '..';
import EditFilter from '.';

// The listing and filter states the Resources page shares through its context.
const EditFilterWithContext = (): JSX.Element => {
  const listingState = useListing();
  const filterState = useFilter();

  return (
    <Context.Provider
      value={{ ...listingState, ...filterState } as unknown as ResourceContext}
    >
      <EditFilter />
    </Context.Provider>
  );
};

export const Default = (): JSX.Element => (
  <TestQueryProvider>
    <Provider>
      <div style={{ height: '100vh' }}>
        <EditFilterWithContext />
        <EditFiltersPanel />
      </div>
    </Provider>
  </TestQueryProvider>
);
