<<<<<<< HEAD
import axios from 'axios';
import { render, act, waitFor, RenderResult } from '@testing-library/react';
import { Provider } from 'jotai';

import { refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import useFilter from '../../testUtils/useFilter';
import useListing from '../useListing';
import Context, { ResourceContext } from '../../testUtils/Context';
import useLoadDetails from '../../testUtils/useLoadDetails';
=======
import * as React from 'react';

import axios from 'axios';
import { useSelector } from 'react-redux';
import { render, act, waitFor } from '@testing-library/react';

import useFilter from '../../Filter/useFilter';
import useListing from '../useListing';
import Context, { ResourceContext } from '../../Context';
import useDetails from '../../Details/useDetails';
>>>>>>> centreon/dev-21.10.x

import useLoadResources from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

<<<<<<< HEAD
const mockUser = {
  locale: 'en',
  timezone: 'Europe/Paris',
};
const mockRefreshInterval = 60;
=======
jest.mock('react-redux', () => ({
  ...(jest.requireActual('react-redux') as jest.Mocked<unknown>),
  useSelector: jest.fn(),
}));
>>>>>>> centreon/dev-21.10.x

let context: ResourceContext;

const LoadResourcesComponent = (): JSX.Element => {
  useLoadResources();

<<<<<<< HEAD
  return <div />;
=======
  return <></>;
>>>>>>> centreon/dev-21.10.x
};

const TestComponent = (): JSX.Element => {
  const filterState = useFilter();
  const listingState = useListing();
<<<<<<< HEAD
  const detailsState = useLoadDetails();
=======
  const detailsState = useDetails();
>>>>>>> centreon/dev-21.10.x

  context = {
    ...filterState,
    ...listingState,
    ...detailsState,
  } as ResourceContext;

  return (
    <Context.Provider value={context}>
      <LoadResourcesComponent />
    </Context.Provider>
  );
};

<<<<<<< HEAD
const TestComponentWithJotai = (): JSX.Element => (
  <Provider
    initialValues={[
      [userAtom, mockUser],
      [refreshIntervalAtom, mockRefreshInterval],
    ]}
  >
    <TestComponent />
  </Provider>
);

const renderLoadResources = (): RenderResult =>
  render(<TestComponentWithJotai />);

describe(useLoadResources, () => {
  beforeEach(() => {
=======
const appState = {
  intervals: {
    AjaxTimeReloadMonitoring: 60,
  },
};

const mockedSelector = useSelector as jest.Mock;

describe(useLoadResources, () => {
  beforeEach(() => {
    mockedSelector.mockImplementation((callback) => {
      return callback(appState);
    });

>>>>>>> centreon/dev-21.10.x
    mockedAxios.get.mockResolvedValue({
      data: {
        meta: {
          limit: 30,
          page: 1,
          total: 0,
        },
        result: [],
      },
    });
  });

  afterEach(() => {
    mockedAxios.get.mockReset();
  });

  const testCases = [
    [
      'sort',
<<<<<<< HEAD
      (): void => context.setCriteria?.({ name: 'sort', value: ['a', 'asc'] }),
      2,
    ],
    ['limit', (): void => context.setLimit?.(20), 2],
    [
      'search',
      (): void => context.setCriteria?.({ name: 'search', value: 'toto' }),
      2,
=======
      (): void => context.setCriteria({ name: 'sort', value: ['a', 'asc'] }),
    ],
    ['limit', (): void => context.setLimit(20), '20'],
    [
      'search',
      (): void => context.setCriteria({ name: 'search', value: 'toto' }),
>>>>>>> centreon/dev-21.10.x
    ],
    [
      'states',
      (): void =>
<<<<<<< HEAD
        context.setCriteria?.({
          name: 'states',
          value: [{ id: 'unhandled', name: 'Unhandled problems' }],
        }),
      2,
=======
        context.setCriteria({
          name: 'states',
          value: [{ id: 'unhandled', name: 'Unhandled problems' }],
        }),
>>>>>>> centreon/dev-21.10.x
    ],
    [
      'statuses',
      (): void =>
<<<<<<< HEAD
        context.setCriteria?.({
          name: 'statuses',
          value: [{ id: 'OK', name: 'Ok' }],
        }),
      2,
=======
        context.setCriteria({
          name: 'statuses',
          value: [{ id: 'OK', name: 'Ok' }],
        }),
>>>>>>> centreon/dev-21.10.x
    ],
    [
      'resourceTypes',
      (): void =>
<<<<<<< HEAD
        context.setCriteria?.({
          name: 'resource_types',
          value: [{ id: 'host', name: 'Host' }],
        }),
      2,
=======
        context.setCriteria({
          name: 'resource_types',
          value: [{ id: 'host', name: 'Host' }],
        }),
>>>>>>> centreon/dev-21.10.x
    ],
    [
      'hostGroups',
      (): void =>
<<<<<<< HEAD
        context.setCriteria?.({
          name: 'host_groups',
          value: [{ id: 0, name: 'Linux-servers' }],
        }),
      2,
=======
        context.setCriteria({
          name: 'host_groups',
          value: [{ id: 0, name: 'Linux-servers' }],
        }),
>>>>>>> centreon/dev-21.10.x
    ],
    [
      'serviceGroups',
      (): void =>
<<<<<<< HEAD
        context.setCriteria?.({
          name: 'service_groups',
          value: [{ id: 1, name: 'Web-services' }],
        }),
      2,
=======
        context.setCriteria({
          name: 'service_groups',
          value: [{ id: 1, name: 'Web-services' }],
        }),
>>>>>>> centreon/dev-21.10.x
    ],
  ];

  it.each(testCases)(
    'resets the page to 1 when %p is changed and current filter is applied',
<<<<<<< HEAD
    async (_, setter, numberOfCalls) => {
      renderLoadResources();

      await waitFor(() => {
        expect(mockedAxios.get).toHaveBeenCalledTimes(numberOfCalls as number);
      });

      act(() => {
        context.setPage?.(2);
=======
    async (_, setter) => {
      render(<TestComponent />);

      await waitFor(() => {
        expect(mockedAxios.get).toHaveBeenCalledTimes(2);
      });

      act(() => {
        context.setPage(2);
>>>>>>> centreon/dev-21.10.x
      });

      await waitFor(() => {
        expect(mockedAxios.get).toHaveBeenCalled();
      });

      act(() => {
        (setter as () => void)();
<<<<<<< HEAD
        context.applyCurrentFilter?.();
=======
        context.applyCurrentFilter();
>>>>>>> centreon/dev-21.10.x
      });

      await waitFor(() => {
        expect(context.page).toEqual(1);
        expect(mockedAxios.get).toHaveBeenCalled();
      });
    },
  );
});
