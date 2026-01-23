import { equals } from 'ramda';
import { lazy } from 'react';

import { FederatedModule } from '../../../../federatedModules/models';
import { PanelConfiguration } from '../models';

const testWidgets =
  equals(window.Cypress?.testingType, 'component') &&
  !equals(process.env.NODE_ENV, 'production')
    ? [
        { name: 'data' },
        { name: 'input', panelDefaultHeight: 6, panelDefaultWidth: 12 },
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
    panelDefaultHeight: 6,
    panelDefaultWidth: 12,
    panelMinHeight: 6,
    panelMinWidth: 12
  },
  {
    name: 'batimeline',
    panelDefaultHeight: 3,
    panelDefaultWidth: 12,
    panelMinHeight: 3,
    panelMinWidth: 3
  },
  {
    name: 'baavailability',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 2,
    panelMinWidth: 4
  },
  {
    name: 'metriccapacityplanning',
    panelDefaultHeight: 5,
    panelDefaultWidth: 12,
    panelMinHeight: 4,
    panelMinWidth: 8
  },
  {
    name: 'clock',
    panelDefaultHeight: 3,
    panelDefaultWidth: 6
  },
  { name: 'generictext', panelDefaultHeight: 3, panelDefaultWidth: 6 },
  {
    name: 'graph',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 3,
    panelMinWidth: 8
  },
  {
    name: 'baavailabilityhistory',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 3,
    panelMinWidth: 8
  },
  {
    name: 'groupmonitoring',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 3,
    panelMinWidth: 8
  },
  {
    name: 'resourcestable',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 4,
    panelMinWidth: 6
  },
  { name: 'singlemetric', panelDefaultWidth: 4, panelMinWidth: 2 },
  { name: 'statuschart', panelMinHeight: 3, panelMinWidth: 4 },
  { name: 'statusgrid', panelDefaultHeight: 3 },
  { name: 'topbottom' },
  { name: 'webpage' },
  { name: 'mbinearsaturationstorage', panelMinHeight: 3, panelMinWidth: 14 },
  {
    name: 'hgavailabilityhistory',
    panelDefaultHeight: 4,
    panelDefaultWidth: 12,
    panelMinHeight: 3,
    panelMinWidth: 8
  }
];

export const internalWidgetComponents: Array<FederatedModule> =
  internalWidgets.map((widget) => ({
    Component: lazy(() => import(`./centreon-widget-${widget.name}/src`)),
    federatedComponentsConfiguration: [
      {
        federatedComponents: [],
        path: `/widgets/${widget.name}`,
        ...widget
      }
    ],
    federatedPages: [],
    moduleFederationName: `centreon-widget-${widget.name}`,
    moduleName: `centreon-widget-${widget.name}`,
    properties: require(`./centreon-widget-${widget.name}/properties.json`),
    remoteEntry: ''
  }));
