import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, propOr } from 'ramda';

import { useDeepCompare, useFetchQuery } from '@centreon/ui';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import {
  dashboardsEndpoint,
  getPublicDashboardEndpoint
} from '../../../api/endpoints';
import { Dashboard, DashboardPanel, resource } from '../../../api/models';
import {
  dashboardDecoder,
  publicDashboardDecoder
} from '../../../api/decoders';
import { FederatedModule } from '../../../../federatedModules/models';
import { useDashboardUserPermissions } from '../../../components/DashboardLibrary/DashboardUserPermissions/useDashboardUserPermissions';
import { Panel, PanelConfiguration } from '../models';
import {
  dashboardAtom,
  dashboardRefreshIntervalAtom,
  hasEditPermissionAtom,
  panelsLengthAtom
} from '../atoms';

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
    data: equals(panel.widgetSettings.data, [])
      ? {}
      : panel.widgetSettings.data || null,
    h: panel.layout.height,
    i: `${panel.id}`,
    minH:
      federatedWidget?.federatedComponentsConfiguration[0].panelMinHeight || 2,
    minW:
      federatedWidget?.federatedComponentsConfiguration[0].panelMinWidth || 2,
    name: panel.name,
    options: panel.widgetSettings.options,
    panelConfiguration: federatedWidget
      ?.federatedComponentsConfiguration[0] as PanelConfiguration,
    static: staticPanel,
    w: panel.layout.width,
    x: panel.layout.x,
    y: panel.layout.y
  };
};

export const routerParams = {
  useParams
};

export const getPanels = (dashboard?: Dashboard): Array<DashboardPanel> =>
  propOr([] as Array<DashboardPanel>, 'panels', dashboard);

type UseDashboardDetailsProps = {
  dashboardId: string | number | null;
  isOnPublicPage: boolean;
  playlistHash?: string;
  suspense?: false;
  viewOnly?: boolean;
};

const useDashboardDetails = ({
  dashboardId,
  viewOnly,
  isOnPublicPage,
  playlistHash
}: UseDashboardDetailsProps): UseDashboardDetailsState => {
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const setDashboard = useSetAtom(dashboardAtom);
  const setPanelsLength = useSetAtom(panelsLengthAtom);
  const setHasEditPermission = useSetAtom(hasEditPermissionAtom);
  const setDashboardRefreshInterval = useSetAtom(dashboardRefreshIntervalAtom);

  const decoder = isOnPublicPage ? publicDashboardDecoder : dashboardDecoder;
  const endpoint =
    isOnPublicPage && playlistHash
      ? getPublicDashboardEndpoint({ dashboardId, playlistID: playlistHash })
      : `${dashboardsEndpoint}/${dashboardId}`;

  const { data: dashboard } = useFetchQuery({
    decoder,
    getEndpoint: () => endpoint,
    getQueryKey: () => [resource.dashboard, dashboardId],
    queryOptions: {
      enabled: !!(playlistHash || dashboardId)
    }
  });

  const { hasEditPermission } = useDashboardUserPermissions();

  const panels = getPanels(dashboard);

  useEffect(
    () => {
      setDashboard({
        layout:
          panels.map((panel) => formatPanel({ federatedWidgets, panel })) || []
      });
      setPanelsLength(panels.length);
    },
    useDeepCompare([panels, federatedWidgets])
  );

  useEffect(() => {
    if (!dashboard || viewOnly) {
      return;
    }

    setHasEditPermission(hasEditPermission(dashboard));
    setDashboardRefreshInterval(dashboard.refresh);
  }, [dashboard]);

  useEffect(() => {
    return () => {
      setDashboardRefreshInterval(undefined);
    };
  }, []);

  return {
    dashboard,
    panels
  };
};

export default useDashboardDetails;
