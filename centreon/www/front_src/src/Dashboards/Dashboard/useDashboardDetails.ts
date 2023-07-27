import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';
import { equals, propOr } from 'ramda';

import { useDeepCompare, useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';
import { Dashboard, DashboardPanel, resource } from '../api/models';
import { dashboardDecoder } from '../api/decoders';
import useFederatedWidgets from '../../federatedModules/useFederatedWidgets';
import { FederatedModule } from '../../federatedModules/models';

import { Panel, PanelConfiguration } from './models';
import { dashboardAtom } from './atoms';

interface UseDashboardDetailsState {
  dashboard?: Dashboard;
  panels?: Array<DashboardPanel>;
}

interface FormatPanelProps {
  federatedWidgets: Array<FederatedModule> | null;
  panel: DashboardPanel;
  staticPanel?: boolean;
}

export const formatPanel = ({
  panel,
  staticPanel = true,
  federatedWidgets = []
}: FormatPanelProps): Panel => {
  const federatedWidget = (federatedWidgets || []).find(({ moduleName }) =>
    equals(moduleName, panel.name)
  );

  return {
    h: panel.layout.height,
    i: `${panel.id}`,
    minH: panel.layout.minHeight,
    minW: panel.layout.minWidth,
    name: panel.name,
    options: panel.widgetSettings,
    panelConfiguration:
      federatedWidget?.federatedComponentsConfiguration as PanelConfiguration,
    static: staticPanel,
    w: panel.layout.width,
    x: panel.layout.x,
    y: panel.layout.y
  };
};

export const routerParams = {
  useParams
};

const getPanels = (dashboard?: Dashboard): Array<DashboardPanel> =>
  propOr([] as Array<DashboardPanel>, 'panels', dashboard);

type UseDashboardDetailsProps = {
  dashboardId: string;
};

const useDashboardDetails = ({
  dashboardId
}: UseDashboardDetailsProps): UseDashboardDetailsState => {
  const setDashboard = useSetAtom(dashboardAtom);

  const { federatedWidgets } = useFederatedWidgets();

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
    getQueryKey: () => [resource.dashboard, dashboardId]
  });

  const panels = getPanels(dashboard);

  useEffect(() => {
    setDashboard({
      layout:
        panels.map((panel) => formatPanel({ federatedWidgets, panel })) || []
    });
  }, useDeepCompare([panels, federatedWidgets]));

  return {
    dashboard,
    panels
  };
};

export default useDashboardDetails;
