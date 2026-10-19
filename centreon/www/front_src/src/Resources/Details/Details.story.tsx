import {
  SnackbarProvider,
  setUrlQueryParameters,
  TestQueryProvider
} from '@centreon/ui';
import { aclAtom, refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { LocationRecorder } from '../../../../../playwright/gallery/storyHelpers';
import Details from '.';
import {
  openDetailsTabIdAtom,
  panelWidthStorageAtom,
  selectedResourcesDetailsAtom
} from './detailsAtoms';
import useDetails from './useDetails';
import useLoadDetails from './useLoadDetails';

const actions = {
  acknowledgement: true,
  check: true,
  comment: true,
  disacknowledgement: true,
  downtime: true,
  forced_check: true,
  submit_status: true
};

const tabIds = { details: 0, timeline: 2 };

const DetailsTest = (): JSX.Element => {
  useDetails();
  useLoadDetails();

  return (
    <div style={{ height: '100vh' }}>
      <Details />
    </div>
  );
};

interface Props {
  panelWidth?: number;
  tab?: keyof typeof tabIds;
}

export const Default = ({
  panelWidth,
  tab = 'details'
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      alias: 'Admin',
      default_page: '/monitoring/resources',
      isExportButtonEnabled: true,
      locale: 'en',
      name: 'Admin',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: 'compact'
    } as never);
    created.set(aclAtom, {
      actions: { host: actions, metaservice: actions, service: actions }
    } as never);
    created.set(refreshIntervalAtom, 60);
    created.set(selectedResourcesDetailsAtom, {
      parentResourceId: undefined,
      parentResourceType: undefined,
      resourceId: 1,
      resourcesDetailsEndpoint:
        '/api/latest/monitoring/resources/hosts/1/services/1'
    });
    created.set(openDetailsTabIdAtom, tabIds[tab]);
    if (panelWidth) {
      created.set(panelWidthStorageAtom, panelWidth);
    }
    setUrlQueryParameters([
      {
        name: 'details',
        value: {
          id: 1,
          resourcesDetailsEndpoint:
            'api/latest/monitoring/resources/hosts/1/services/1',
          tab,
          type: 'service',
          uuid: 'h1-s1'
        }
      }
    ]);

    return created;
  }, [panelWidth, tab]);

  return (
    <SnackbarProvider>
      <TestQueryProvider>
        <Provider store={store}>
          <MemoryRouter>
            <DetailsTest />
            <LocationRecorder />
          </MemoryRouter>
        </Provider>
      </TestQueryProvider>
    </SnackbarProvider>
  );
};
