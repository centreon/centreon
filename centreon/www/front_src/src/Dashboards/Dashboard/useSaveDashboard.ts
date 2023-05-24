import { useParams } from 'react-router-dom';
import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { getPanelsEndpoint } from './api/endpoints';
import { dashboardAtom, switchPanelsEditionModeDerivedAtom } from './atoms';
import { Panel, PanelDetailsAPI } from './models';
import { labelYourDashboardHasBeenSaved } from './translatedLabels';

const formatPanelsToAPI = (layout: Array<Panel>): Array<PanelDetailsAPI> =>
  layout.map(
    ({ h, i, panelConfiguration, w, x, y, minH, minW, options, name }) => ({
      id: Number(i),
      layout: {
        height: h,
        min_height: minH || 0,
        min_width: minW || 0,
        width: w,
        x,
        y
      },
      name,
      widget_settings: (options || {}) as {
        [key: string]: unknown;
      },
      widget_type: panelConfiguration.path
    })
  );

interface UseSaveDashboardState {
  isSaving: boolean;
  saveDashboard: () => void;
}

const useSaveDashboard = (): UseSaveDashboardState => {
  const { t } = useTranslation();
  const { dashboardId } = useParams();

  const queryClient = useQueryClient();

  const dashboard = useAtomValue(dashboardAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: () => getPanelsEndpoint(dashboardId),
    method: Method.PUT
  });

  const saveDashboard = (): void => {
    mutateAsync(formatPanelsToAPI(dashboard.layout)).then(() => {
      showSuccessMessage(t(labelYourDashboardHasBeenSaved));
      switchPanelsEditionMode(false);
      queryClient.invalidateQueries({
        queryKey: ['dashboard', dashboardId, 'panels']
      });
    });
  };

  return { isSaving: isMutating, saveDashboard };
};

export default useSaveDashboard;
