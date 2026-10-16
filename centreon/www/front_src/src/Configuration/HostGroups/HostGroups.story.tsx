import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useEffect, useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import HostGroups from '.';
import {
  getDetailsResponse,
  getListingResponse,
  getPayload,
  hostsListEmptyResponse,
  listImagesResponse
} from './Specs/utils';

interface Props {
  hasWriteAccess?: boolean;
  isCloudPlatform?: boolean;
}

export const Default = ({
  hasWriteAccess = true,
  isCloudPlatform = false
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(platformFeaturesAtom, {
      featureFlags: {},
      isCloudPlatform
    } as never);
    created.set(userPermissionsAtom, {
      configuration_host_group_write: hasWriteAccess
    } as never);

    return created;
  }, [hasWriteAccess, isCloudPlatform]);

  return (
    <MemoryRouter>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <HostGroups />
            </div>
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    </MemoryRouter>
  );
};

const data = {
  details: {
    cloud: getDetailsResponse({ isCloudPlatform: true }),
    onPrem: getDetailsResponse({})
  },
  hostGroups: getListingResponse('host group'),
  hosts: getListingResponse('host'),
  hostsEmpty: hostsListEmptyResponse,
  images: listImagesResponse,
  payload: {
    cloud: getPayload({ isCloudPlatform: true }),
    onPrem: getPayload({})
  },
  rules: getListingResponse('rule')
};

declare global {
  interface Window {
    hostGroupsData?: typeof data;
  }
}

// Data story: the test data module imports images, which Node cannot load, so
// specs read it from the browser.
export const Data = (): JSX.Element => {
  useEffect(() => {
    window.hostGroupsData = data;
  }, []);

  return <p>host groups data ready</p>;
};
