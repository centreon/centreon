import { equals } from 'ramda';
import { lazy } from 'react';
import { FederatedModule } from '../../../../federatedModules/models';
import { PanelConfiguration } from '../models';

const testWidgets =
  equals(window.Cypress?.testingType, 'component') &&
  !equals(process.env.NODE_ENV, 'production')
    ? [
        { name: 'data' },
        { name: 'input', panelDefaultWidth: 6, panelDefaultHeight: 6 },
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
    panelMinWidth: 6,
    panelDefaultWidth: 6,
    panelDefaultHeight: 6
  },
  {
    name: 'clock',
    panelDefaultHeight: 3,
    panelDefaultWidth: 3
  },
  { name: 'generictext', panelDefaultWidth: 3, panelDefaultHeight: 3 },
  {
    name: 'graph',
    panelMinHeight: 3,
    panelMinWidth: 4,
    panelDefaultHeight: 4,
    panelDefaultWidth: 6
  },
  {
    name: 'groupmonitoring',
    panelMinWidth: 4,
    panelMinHeight: 3,
    panelDefaultWidth: 6,
    panelDefaultHeight: 4
  },
  {
    name: 'resourcestable',
    panelMinWidth: 6,
    panelMinHeight: 3,
    panelDefaultHeight: 4,
    panelDefaultWidth: 6
  },
  { name: 'singlemetric' },
  { name: 'statuschart', panelMinWidth: 2, panelMinHeight: 3 },
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
