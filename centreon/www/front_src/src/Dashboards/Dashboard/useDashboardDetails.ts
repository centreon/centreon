import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';
import { propOr } from 'ramda';

import { useFetchQuery } from '@centreon/ui';

import { dashboardDetailsDecoder } from './api/decoders';
import { DashboardDetails, Panel, PanelDetails } from './models';
import { dashboardAtom } from './atoms';
import { getDashboardEndpoint } from './api/endpoints';

interface UseDashboardDetailsState {
  dashboard?: DashboardDetails;
  panels?: Array<PanelDetails>;
}

interface FormatPanelProps {
  panel: PanelDetails;
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

const getPanels = (dashboard?: DashboardDetails): Array<PanelDetails> =>
  propOr([] as Array<PanelDetails>, 'panels', dashboard);

const useDashboardDetails = (): UseDashboardDetailsState => {
  const { dashboardId } = routerParams.useParams();

  const setDashboard = useSetAtom(dashboardAtom);

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDetailsDecoder,
    getEndpoint: () => getDashboardEndpoint(dashboardId),
    getQueryKey: () => ['dashboard', dashboardId]
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
