import { federatedWidgetsAtom } from '@centreon/ui-context';

import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';

import { Dashboard, resource } from '../../../api/models';
import { dashboardAtom } from '../atoms';
import { Panel } from '../models';
import { formatPanel, getPanels, routerParams } from './useDashboardDetails';

const useResetDashboardFromSavedState = (): (() => Array<Panel>) => {
  const { dashboardId } = routerParams.useParams();
  const queryClient = useQueryClient();

  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const setDashboard = useSetAtom(dashboardAtom);

  return useCallback((): Array<Panel> => {
    const dashboard = queryClient.getQueryData<Dashboard>([
      resource.dashboard,
      dashboardId
    ]);

    const layout = getPanels(dashboard).map((panel) =>
      formatPanel({ federatedWidgets, panel })
    );

    setDashboard({ layout });

    return layout;
  }, [federatedWidgets, dashboardId]);
};

export default useResetDashboardFromSavedState;
