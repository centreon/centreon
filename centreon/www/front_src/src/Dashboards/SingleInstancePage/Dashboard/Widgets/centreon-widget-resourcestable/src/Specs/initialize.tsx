import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  type Acl,
  aclAtom,
  isOnPublicPageAtom,
  type PlatformVersions,
  platformVersionsAtom
} from '@centreon/ui-context';

import { interceptAPIRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter } from 'react-router';

import {
  countResourcesEndpoint,
  resourcesEndpoint,
  viewByHostEndpoint
} from '../api/endpoints';
import Widget from '../index';
import type { PanelOptions } from '../models';
import {
  emptyListingResponse,
  getListingResponse,
  panelData,
  panelOptions
} from './utils';

interface Props {
  // The number of resources the count endpoint answers.
  count?: number;
  isEditingDashboard?: boolean;
  isFromPreview?: boolean;
  options?: Partial<PanelOptions>;
  playlistHash?: string;
}

const initialize = ({
  count = 25,
  isEditingDashboard = false,
  isFromPreview = false,
  options = {},
  playlistHash
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(isOnPublicPageAtom, false);
  store.set(aclAtom, { actions: { host: {}, service: {} } } as Acl);
  store.set(platformVersionsAtom, {
    modules: {},
    web: { fix: '0', major: '26', minor: '10', version: '26.10.0' },
    widgets: {}
  } as unknown as PlatformVersions);

  interceptAPIRequest({
    alias: 'getResources',
    method: Method.GET,
    path: `./api/latest${resourcesEndpoint}`,
    response: getListingResponse()
  });

  interceptAPIRequest({
    alias: 'getResourcesByHost',
    method: Method.GET,
    path: `./api/latest${viewByHostEndpoint}`,
    response: emptyListingResponse
  });

  interceptAPIRequest({
    alias: 'getResourcesCount',
    method: Method.GET,
    path: `./api/latest${countResourcesEndpoint}`,
    response: { count, is_approximate: false }
  });

  return render(
    <BrowserRouter>
      <TestQueryProvider>
        <SnackbarProvider>
          <Provider store={store}>
            <div style={{ height: '100vh', width: '100%' }}>
              <Widget
                dashboardId={1}
                globalRefreshInterval={{ interval: 30, type: 'manual' }}
                hasDescription={false}
                id="1"
                isEditingDashboard={isEditingDashboard}
                isFromPreview={isFromPreview}
                isInViewport
                panelData={panelData}
                panelOptions={{ ...panelOptions, ...options }}
                playlistHash={playlistHash}
                refreshCount={0}
                widgetPrefixQuery="widget"
              />
            </div>
          </Provider>
        </SnackbarProvider>
      </TestQueryProvider>
    </BrowserRouter>
  );
};

export default initialize;
