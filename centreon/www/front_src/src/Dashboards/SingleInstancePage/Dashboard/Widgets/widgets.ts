import { equals } from 'ramda';
import { lazy } from 'react';
import { FederatedModule } from '../../../../federatedModules/models';

const testWidgets =
  equals(window.Cypress?.testingType, 'component') &&
  !equals(process.env.NODE_ENV, 'production')
    ? [
        { name: 'data' },
        { name: 'input' },
        { name: 'singledata' },
        { name: 'text' }
      ]
    : [];

const internalWidgets = [
  ...testWidgets,
  {
    name: 'batree',
    panelMinHeight: 5,
    panelMinWidth: 6
  },
  {
    name: 'clock'
  },
  { name: 'generictext' },
  {
    name: 'graph',
    panelMinHeight: 3,
    panelMinWidth: 4
  },
  {
    name: 'groupmonitoring',
    panelMinWidth: 4,
    panelMinHeight: 3
  },
  {
    name: 'resourcestable',
    panelMinWidth: 6,
    panelMinHeight: 3
  },
  { name: 'singlemetric' },
  { name: 'statuschart' },
  { name: 'statusgrid' },
  { name: 'topbottom' },
  { name: 'webpage' }
];

export const internalWidgetComponents: Array<FederatedModule> =
  internalWidgets.map((widget) => ({
    moduleName: `centreon-widget-${widget.name}`,
    remoteEntry: '',
    moduleFederationName: `centreon-widget-${widget.name}`,
    federatedPages: [],
    federatedComponentsConfiguration: [
      {
        path: `/widgets/${widget.name}`,
        federatedComponents: [],
        ...widget
      }
    ],
    Component: lazy(() => import(`./centreon-widget-${widget.name}/src`)),
    properties: require(`./centreon-widget-${widget.name}/properties.json`)
  }));
