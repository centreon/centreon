import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';
import { propOr } from 'ramda';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';
import { Dashboard, DashboardPanel, resource } from '../api/models';
import { dashboardDecoder } from '../api/decoders';

import { Panel } from './models';
import { dashboardAtom } from './atoms';

interface UseDashboardDetailsState {
  dashboard?: Dashboard;
  panels?: Array<DashboardPanel>;
}

interface FormatPanelProps {
  panel: DashboardPanel;
  staticPanel?: boolean;
}

export const formatPanel = ({
  panel,
  staticPanel = true
}: FormatPanelProps): Panel => ({
  h: panel.layout.height,
  i: `${panel.id}`,
  minH: panel.layout.minHeight,
  minW: panel.layout.minWidth,
  name: panel.name,
  options: panel.widgetSettings,
  panelConfiguration: {
    path: panel.widgetType
  },
  static: staticPanel,
  w: panel.layout.width,
  x: panel.layout.x,
  y: panel.layout.y
});

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

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
    getQueryKey: () => [resource.dashboards, dashboardId]
  });

  const panels = getPanels(dashboard);

  useEffect(() => {
    setDashboard({
      layout: panels.map((panel) => formatPanel({ panel })) || []
    });
  }, [panels]);

  return {
    dashboard,
    panels
  };
};

export default useDashboardDetails;
