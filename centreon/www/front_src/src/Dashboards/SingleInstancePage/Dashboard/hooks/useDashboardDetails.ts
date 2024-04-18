import { useEffect } from 'react';

import { useParams } from 'react-router-dom';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, propOr } from 'ramda';

import { useDeepCompare, useFetchQuery } from '@centreon/ui';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import { dashboardsEndpoint } from '../../../api/endpoints';
import { Dashboard, DashboardPanel, resource } from '../../../api/models';
import { dashboardDecoder } from '../../../api/decoders';
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
    minH: panel.layout.minHeight,
    minW: panel.layout.minWidth,
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
  suspense?: false;
  viewOnly?: boolean;
};

const useDashboardDetails = ({
  dashboardId,
  viewOnly
}: UseDashboardDetailsProps): UseDashboardDetailsState => {
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const setDashboard = useSetAtom(dashboardAtom);
  const setPanelsLength = useSetAtom(panelsLengthAtom);
  const setHasEditPermission = useSetAtom(hasEditPermissionAtom);
  const setDashboardRefreshInterval = useSetAtom(dashboardRefreshIntervalAtom);

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
    getQueryKey: () => [resource.dashboard, dashboardId],
    queryOptions: {
      enabled: !!dashboardId
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
