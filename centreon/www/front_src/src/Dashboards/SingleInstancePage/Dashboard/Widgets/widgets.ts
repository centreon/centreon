import { equals } from 'ramda';
import { lazy } from 'react';
import { FederatedModule } from '../../../../federatedModules/models';
import { PanelConfiguration } from '../models';

const testWidgets =
  equals(window.Cypress?.testingType, 'component') &&
  !equals(process.env.NODE_ENV, 'production')
    ? [
        { name: 'data' },
        { name: 'input', panelDefaultWidth: 12, panelDefaultHeight: 6 },
        { name: 'singledata' },
        { name: 'text' }
      ]
    : [];

const internalWidgets: Array<
  Omit<PanelConfiguration, 'path'> & { name: string }
> = [
  ...testWidgets,
  {
    name: 'batree',
    panelMinHeight: 6,
    panelMinWidth: 12,
    panelDefaultWidth: 12,
    panelDefaultHeight: 6
  },
  {
    name: 'batimeline',
    panelDefaultHeight: 3,
    panelDefaultWidth: 12,
    panelMinWidth: 3,
    panelMinHeight: 3
  },
  {
    name: 'baavailability',
    panelDefaultWidth: 12,
    panelDefaultHeight: 4,
    panelMinHeight: 2,
    panelMinWidth: 4
  },
  {
    name: 'metriccapacityplanning',
    panelMinHeight: 4,
    panelMinWidth: 8,
    panelDefaultHeight: 5,
    panelDefaultWidth: 12
  },
  {
    name: 'clock',
    panelDefaultHeight: 3,
    panelDefaultWidth: 6
  },
  { name: 'generictext', panelDefaultWidth: 6, panelDefaultHeight: 3 },
  {
    name: 'graph',
    panelMinHeight: 3,
    panelMinWidth: 8,
    panelDefaultHeight: 4,
    panelDefaultWidth: 12
  },
  {
    name: 'groupmonitoring',
    panelMinWidth: 8,
    panelMinHeight: 3,
    panelDefaultWidth: 12,
    panelDefaultHeight: 4
  },
  {
    name: 'resourcestable',
    panelMinWidth: 12,
    panelMinHeight: 3,
    panelDefaultHeight: 4,
    panelDefaultWidth: 12
  },
  { name: 'singlemetric', panelMinWidth: 4, panelDefaultWidth: 4 },
  { name: 'statuschart', panelMinWidth: 4, panelMinHeight: 3 },
  { name: 'statusgrid', panelDefaultHeight: 3 },
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
