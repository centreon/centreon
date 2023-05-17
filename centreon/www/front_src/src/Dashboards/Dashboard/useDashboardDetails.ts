import { useParams } from 'react-router-dom';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';

import { dashboardDetailsDecoder, panelsDetailsDecoder } from './api/decoders';
import { DashboardDetails, Panel, PanelDetails } from './models';
import { useEffect } from 'react';
import { dashboardAtom } from './atoms';
import { useSetAtom } from 'jotai';

interface UseDashboardDetailsState {
  dashboard?: DashboardDetails;
  panels?: Array<PanelDetails>;
}

interface FormatPanelProps {
  panel: PanelDetails;
  staticPanel?: boolean;
}

const formatPanel = ({ panel, staticPanel = true }: FormatPanelProps): Panel => ({
  h: panel.layout.height,
  i: `${panel.id}`,
  minH: panel.layout.minHeight,
  minW: panel.layout.minWidth,
  w: panel.layout.width,
  x: panel.layout.x,
  y: panel.layout.y,
  options: panel.widgetSettings,
  panelConfiguration: {
    path: panel.widgetType
  },
  static: staticPanel
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
      layout: panels?.map(panel => formatPanel({ panel })) || []
    });
  }, [panels])

  return {
    dashboard,
    panels
  };
};

export default useDashboardDetails;
