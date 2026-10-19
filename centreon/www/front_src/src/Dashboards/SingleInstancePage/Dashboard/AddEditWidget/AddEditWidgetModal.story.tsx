import { TestQueryProvider } from '@centreon/ui';
import {
  federatedWidgetsAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { createStore, Provider, useAtomValue } from 'jotai';
import { useMemo } from 'react';

import { federatedWidgetsPropertiesAtom } from '../../../../federatedModules/atoms';
import { dashboardAtom, hasEditPermissionAtom, isEditingAtom } from '../atoms';
import widgetClockProperties from '../Widgets/centreon-widget-clock/properties.json';
import widgetDataProperties from '../Widgets/centreon-widget-data/properties.json';
import widgetGenericTextProperties from '../Widgets/centreon-widget-generictext/properties.json';
import widgetGraphProperties from '../Widgets/centreon-widget-graph/properties.json';
import widgetGroupMonitoringProperties from '../Widgets/centreon-widget-groupmonitoring/properties.json';
import widgetInputProperties from '../Widgets/centreon-widget-input/properties.json';
import widgetResourceTableProperties from '../Widgets/centreon-widget-resourcestable/properties.json';
import widgetSingleDataProperties from '../Widgets/centreon-widget-singledata/properties.json';
import widgetSingleMetricProperties from '../Widgets/centreon-widget-singlemetric/properties.json';
import widgetStatusChartProperties from '../Widgets/centreon-widget-statuschart/properties.json';
import widgetStatusGridProperties from '../Widgets/centreon-widget-statusgrid/properties.json';
import widgetTextProperties from '../Widgets/centreon-widget-text/properties.json';
import widgetTopBottomProperties from '../Widgets/centreon-widget-topbottom/properties.json';
import widgetWebPageProperties from '../Widgets/centreon-widget-webpage/properties.json';
import { internalWidgetComponents } from '../Widgets/widgets';
import AddEditWidgetModal from './AddEditWidgetModal';
import { widgetFormInitialDataAtom } from './atoms';

const description = {
  content:
    '{"root":{"children":[{"children":[{"detail":0,"format":0,"mode":"normal","style":"","text":"Description","type":"text","version":1}],"direction":"ltr","format":"","indent":0,"type":"paragraph","version":1}],"direction":"ltr","format":"","indent":0,"type":"root","version":1}}',
  enabled: true
};

// The widget forms the Cypress spec opened the modal with.
const initialForms = {
  add: {
    data: {},
    id: null,
    moduleName: null,
    options: {},
    panelConfiguration: null
  },
  data: {
    data: {
      metrics: [
        {
          criticalHighThreshold: null,
          criticalLowThreshold: null,
          id: 0,
          metrics: [{ id: 0, name: 'ping' }],
          name: 'Service 1',
          unit: '%',
          warningHighThreshold: null,
          warningLowThreshold: null
        }
      ],
      resources: [
        { resources: [{ id: 0, name: 'Host 0' }], resourceType: 'host' }
      ]
    },
    id: 'centreon-widget-data_1',
    moduleName: 'centreon-widget-data',
    options: { description, name: 'Widget name' },
    panelConfiguration: {
      federatedComponents: ['./data'],
      path: '/widgets/data'
    }
  },
  edit: {
    data: {},
    id: 'centreon-widget-text_1',
    moduleName: 'centreon-widget-text',
    options: { description, name: 'Widget name' },
    panelConfiguration: {
      federatedComponents: ['./text'],
      path: '/widgets/text'
    }
  }
};

// The widgets registered in the platform.
const widgetSets = {
  categories: [
    widgetGenericTextProperties,
    widgetClockProperties,
    widgetWebPageProperties,
    widgetTopBottomProperties,
    widgetGroupMonitoringProperties,
    widgetGraphProperties,
    widgetResourceTableProperties,
    widgetSingleMetricProperties,
    widgetStatusGridProperties,
    widgetStatusChartProperties
  ],
  default: [
    widgetTextProperties,
    widgetInputProperties,
    widgetDataProperties,
    widgetGenericTextProperties,
    widgetSingleDataProperties,
    widgetStatusGridProperties,
    widgetSingleMetricProperties,
    widgetGraphProperties,
    widgetTopBottomProperties
  ],
  none: [],
  unknownProperty: [
    {
      description: 'This is the description of the data widget',
      moduleName: 'centreon-widget-data',
      options: {
        threshold: { defaultValue: '', label: 'threshold', type: 'unknown' }
      },
      title: 'Generic data (example)',
      widgetType: 'realtime'
    }
  ]
};

// Records the dashboard layout the modal writes on save.
const DashboardRecorder = (): JSX.Element => {
  const dashboard = useAtomValue(dashboardAtom);

  return (
    <form hidden>
      <input
        data-testid="dashboard-layout"
        readOnly
        value={JSON.stringify(dashboard.layout)}
      />
    </form>
  );
};

interface Props {
  initialForm?: keyof typeof initialForms;
  isEditing?: boolean;
  widgets?: keyof typeof widgetSets;
}

export const Default = ({
  initialForm = 'add',
  isEditing = true,
  widgets = 'default'
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    const properties = widgetSets[widgets];
    // Only the collapsible widgets set changes the registered versions.
    const registered =
      widgets === 'categories' ? widgetSets.categories : widgetSets.default;
    created.set(
      federatedWidgetsAtom,
      widgets === 'none' ? [] : internalWidgetComponents
    );
    created.set(
      federatedWidgetsPropertiesAtom,
      (widgets === 'none' ? null : properties) as never
    );
    created.set(platformVersionsAtom, {
      modules: {},
      web: { version: '23.04.0' },
      widgets: Object.fromEntries(
        registered.map(({ moduleName }) => [moduleName, {}])
      )
    } as never);
    created.set(widgetFormInitialDataAtom, initialForms[initialForm] as never);
    created.set(hasEditPermissionAtom, true);
    created.set(isEditingAtom, isEditing);

    return created;
  }, [initialForm, isEditing, widgets]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <AddEditWidgetModal />
        <DashboardRecorder />
      </Provider>
    </TestQueryProvider>
  );
};
