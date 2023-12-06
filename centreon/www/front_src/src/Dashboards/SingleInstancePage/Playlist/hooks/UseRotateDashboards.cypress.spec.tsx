import { Provider, createStore } from 'jotai';

import { displayedDashboardAtom } from '../atoms';

import useRotationDashboards from './useRotateDashboards';

const TestComponent = ({
  dashboards,
  playlistId,
  rotationTime
}): JSX.Element => {
  useRotationDashboards({ dashboards, playlistId, rotationTime });

  return <div />;
};

const dashboards = [
  {
    id: 1,
    name: 'Dashboard 1'
  },
  {
    id: 2,
    name: 'Dashboard 2'
  },
  {
    id: 3,
    name: 'Dashboard 3'
  }
];

const initialize = (): ReturnType<typeof createStore> => {
  const store = createStore();

  cy.mount({
    Component: (
      <Provider store={store}>
        <TestComponent
          dashboards={dashboards}
          playlistId={1}
          rotationTime={2}
        />
      </Provider>
    )
  });

  return store;
};

describe('UseRotateDashboards', () => {
  it('sets the first dashboard as displayed when initializing the rotation', () => {
    const store = initialize();

    expect(store.get(displayedDashboardAtom)).to.equal(1);
  });
});
