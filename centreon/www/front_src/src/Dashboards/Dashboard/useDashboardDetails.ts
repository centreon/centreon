import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';
import { propOr } from 'ramda';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';

import { dashboardDetailsDecoder } from './api/decoders';
import { DashboardDetails, Panel, PanelDetails } from './models';
import { dashboardAtom } from './atoms';

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

type UseDashboardDetailsProps = {
  dashboardId: string | null;
};

const useDashboardDetails = ({
  dashboardId
}: UseDashboardDetailsProps): UseDashboardDetailsState => {
  if (!dashboardId) throw new Error('dashboardId is required');

  const setDashboard = useSetAtom(dashboardAtom);

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDetailsDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
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
