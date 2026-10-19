import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  additionalResourcesAtom,
  DashboardGlobalRole,
  federatedWidgetsAtom,
  ListingVariant,
  platformVersionsAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import { createStore, Provider, useAtomValue, useSetAtom } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { federatedWidgets } from '../../../../../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/federatedWidgets';
import { federatedWidgetsProperties } from '../../../../../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/federatedWidgetsProperties';
import { version } from '../../../../../../cypress/fixtures/Dashboards/Dashboard/ExpandReduce/version';
import { LocationRecorder } from '../../../../../../playwright/gallery/storyHelpers';
import { federatedWidgetsPropertiesAtom } from '../../../federatedModules/atoms';
import {
  dashboardAtom,
  isEditingAtom,
  isRedirectionBlockedAtom
} from './atoms';
import Dashboard from './Dashboard';
import { routerParams } from './hooks/useDashboardDetails';
import { saveBlockerHooks } from './hooks/useDashboardSaveBlocker';
import widgetGenericTextProperties from './Widgets/centreon-widget-generictext/properties.json';
import widgetInputProperties from './Widgets/centreon-widget-input/properties.json';
import widgetSingleMetricProperties from './Widgets/centreon-widget-singlemetric/properties.json';
import widgetTextProperties from './Widgets/centreon-widget-text/properties.json';
import widgetWebpageProperties from './Widgets/centreon-widget-webpage/properties.json';
import { internalWidgetComponents } from './Widgets/widgets';

const widgetProperties = [
  widgetTextProperties,
  widgetInputProperties,
  widgetGenericTextProperties,
  widgetSingleMetricProperties,
  widgetWebpageProperties
];

const globalRoles = {
  administrator: {
    createDashboards: true,
    globalUserRole: DashboardGlobalRole.administrator,
    manageAllDashboards: true,
    viewDashboards: true
  },
  creator: {
    createDashboards: true,
    globalUserRole: DashboardGlobalRole.creator,
    manageAllDashboards: false,
    viewDashboards: true
  },
  viewer: {
    createDashboards: false,
    globalUserRole: DashboardGlobalRole.viewer,
    manageAllDashboards: false,
    viewDashboards: true
  }
};

// Records the navigation blocker calls (cy.stub in Cypress).
const blockerCalls: Array<string> = [];

// Records the editing state and the layout, and lets specs simulate a
// navigation to another page while the dashboard has unsaved changes.
const DashboardRecorder = (): JSX.Element => {
  const dashboard = useAtomValue(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setRedirectionBlocked = useSetAtom(isRedirectionBlockedAtom);

  return (
    <form hidden>
      <input
        data-testid="dashboard-state"
        readOnly
        value={JSON.stringify({ isEditing, layout: dashboard.layout })}
      />
      <button
        data-testid="block-redirection"
        onClick={() => setRedirectionBlocked(true)}
        type="button"
      />
    </form>
  );
};

interface Props {
  globalRole?: keyof typeof globalRoles;
  isBlocked?: boolean;
  widgets?: 'default' | 'expandReduce';
}

export const Default = ({
  globalRole = 'administrator',
  isBlocked = false,
  widgets = 'default'
}: Props): JSX.Element => {
  routerParams.useParams = (() => ({
    dashboardId: '1'
  })) as typeof routerParams.useParams;
  saveBlockerHooks.useBlocker = (() => ({
    proceed: () => blockerCalls.push('proceed'),
    reset: () => blockerCalls.push('reset'),
    state: isBlocked ? 'blocked' : 'unblocked'
  })) as unknown as typeof saveBlockerHooks.useBlocker;
  Object.assign(window, { blockerCalls });

  const store = useMemo(() => {
    const created = createStore();
    if (widgets === 'expandReduce') {
      created.set(federatedWidgetsAtom, federatedWidgets as never);
      created.set(
        federatedWidgetsPropertiesAtom,
        federatedWidgetsProperties as never
      );
      created.set(platformVersionsAtom, version as never);
    } else {
      created.set(federatedWidgetsAtom, internalWidgetComponents);
      created.set(federatedWidgetsPropertiesAtom, widgetProperties as never);
      created.set(platformVersionsAtom, {
        modules: {},
        web: { version: '23.04.0' },
        widgets: Object.fromEntries(
          widgetProperties.map(({ moduleName }) => [moduleName, {}])
        )
      } as never);
    }
    created.set(userAtom, {
      alias: 'admin',
      dashboard: globalRoles[globalRole],
      isExportButtonEnabled: true,
      locale: 'en',
      name: 'admin',
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    } as never);
    created.set(refreshIntervalAtom, 15);
    created.set(additionalResourcesAtom, [
      { baseEndpoint: '/ba', label: 'BA', resourceType: 'business-activity' }
    ] as never);
    created.set(isEditingAtom, false);

    return created;
  }, [globalRole, widgets]);

  return (
    <div style={{ height: '90vh' }}>
      <TestQueryProvider>
        <MemoryRouter>
          <SnackbarProvider>
            <Provider store={store}>
              <Dashboard />
              <DashboardRecorder />
              <LocationRecorder />
            </Provider>
          </SnackbarProvider>
        </MemoryRouter>
      </TestQueryProvider>
    </div>
  );
};
