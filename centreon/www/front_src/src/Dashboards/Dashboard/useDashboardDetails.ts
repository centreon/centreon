import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';

import { dashboardDetailsDecoder, panelsDetailsDecoder } from './api/decoders';
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

const useDashboardDetails = (): UseDashboardDetailsState => {
  const { dashboardId } = useParams();

  const setDashboard = useSetAtom(dashboardAtom);

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDetailsDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
    getQueryKey: () => ['dashboard', dashboardId]
  });

  const { data: panels } = useFetchQuery({
    decoder: panelsDetailsDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}/panels`,
    getQueryKey: () => ['dashboard', dashboardId, 'panels']
  });

  useEffect(() => {
    setDashboard({
      layout: panels?.map((panel) => formatPanel({ panel })) || []
    });
  }, [panels]);

  return {
    dashboard,
    panels
  };
};

export default useDashboardDetails;
