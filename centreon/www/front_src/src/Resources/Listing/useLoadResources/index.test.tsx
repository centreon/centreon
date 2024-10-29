import { RenderResult, act, render, waitFor } from '@testing-library/react';
import axios from 'axios';
import { Provider, createStore } from 'jotai';

import {
  ListingVariant,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import Context, { ResourceContext } from '../../testUtils/Context';
import useFilter from '../../testUtils/useFilter';
import useLoadDetails from '../../testUtils/useLoadDetails';
import useListing from '../useListing';

import useLoadResources from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const retrievedUser = {
  alias: 'Admin',
  default_page: '/monitoring/resources',
  isExportButtonEnabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false,
  user_interface_density: ListingVariant.compact
};
const mockRefreshInterval = 60;

let context: ResourceContext;

const LoadResourcesComponent = (): JSX.Element => {
  useLoadResources();

  return <div />;
};

const TestComponent = (): JSX.Element => {
  const filterState = useFilter();
  const listingState = useListing();
  const detailsState = useLoadDetails();

  context = {
    ...filterState,
    ...listingState,
    ...detailsState
  } as ResourceContext;

  return (
    <Context.Provider value={context}>
      <LoadResourcesComponent />
    </Context.Provider>
  );
};

const store = createStore();
store.set(userAtom, retrievedUser);
store.set(refreshIntervalAtom, mockRefreshInterval);

const TestComponentWithJotai = (): JSX.Element => (
  <Provider store={store}>
    <TestComponent />
  </Provider>
);

const renderLoadResources = (): RenderResult =>
  render(<TestComponentWithJotai />);

describe(useLoadResources, () => {
  beforeEach(() => {
    mockedAxios.get.mockResolvedValue({
      data: {
        meta: {
          limit: 30,
          page: 1,
          total: 0
        },
        result: []
      }
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  const testCases = [
    [
      'sort',
      (): void => context.setCriteria?.({ name: 'sort', value: ['a', 'asc'] }),
      2
    ],
    ['limit', (): void => context.setLimit?.(20), 3],
    [
      'search',
      (): void => context.setCriteria?.({ name: 'search', value: 'toto' }),
      3
    ],
    [
      'states',
      (): void =>
        context.setCriteria?.({
          name: 'states',
          value: [{ id: 'unhandled', name: 'Unhandled alerts' }]
        }),
      3
    ],
    [
      'statuses',
      (): void =>
        context.setCriteria?.({
          name: 'statuses',
          value: [{ id: 'OK', name: 'Ok' }]
        }),
      3
    ],
    [
      'resourceTypes',
      (): void =>
        context.setCriteria?.({
          name: 'resource_types',
          value: [{ id: 'host', name: 'Host' }]
        }),
      3
    ],
    [
      'hostGroups',
      (): void =>
        context.setCriteria?.({
          name: 'host_groups',
          value: [{ id: 0, name: 'Linux-servers' }]
        }),
      3
    ],
    [
      'serviceGroups',
      (): void =>
        context.setCriteria?.({
          name: 'service_groups',
          value: [{ id: 1, name: 'Web-services' }]
        }),
      3
    ],
    [
      'hostCategories',
      (): void =>
        context.setCriteria?.({
          name: 'host_categories',
          value: [{ id: 0, name: 'Linux' }]
        }),
      3
    ],
    [
      'serviceCategories',
      (): void =>
        context.setCriteria?.({
          name: 'service_categories',
          value: [{ id: 1, name: 'Web-services' }]
        }),
      3
    ]
  ];

  it.each(testCases)(
    'resets the page to 1 when %p is changed and current filter is applied',
    async (_, setter, numberOfCalls) => {
      renderLoadResources();

      await waitFor(() => {
        expect(mockedAxios.get).toHaveBeenCalledTimes(numberOfCalls as number);
      });

      act(() => {
        context.setPage?.(2);
      });

      await waitFor(() => {
        expect(mockedAxios.get).toHaveBeenCalled();
      });

      act(() => {
        (setter as () => void)();
        context.applyCurrentFilter?.();
      });

      await waitFor(() => {
        expect(context.page).toEqual(1);
        expect(mockedAxios.get).toHaveBeenCalled();
      });
    }
  );
});
