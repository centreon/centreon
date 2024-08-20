import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { getDashboardEndpoint } from '../../../api/endpoints';
import { resource } from '../../../api/models';
import { dashboardAtom, switchPanelsEditionModeDerivedAtom } from '../atoms';
import { Panel, PanelDetailsToAPI } from '../models';
import { labelYourDashboardHasBeenSaved } from '../translatedLabels';

import { routerParams } from './useDashboardDetails';

const formatPanelsToAPI = (layout: Array<Panel>): Array<PanelDetailsToAPI> =>
  layout.map(
    ({
      h,
      i,
      panelConfiguration,
      w,
      x,
      y,
      minH,
      minW,
      options,
      data,
      name
    }) => ({
      id: Number(i),
      layout: {
        height: h,
        min_height: minH || 0,
        min_width: minW || 0,
        width: w,
        x,
        y
      },
      name: name || '',
      widget_settings: {
        data,
        options
      },
      widget_type: panelConfiguration.path
    })
  );

interface UseSaveDashboardState {
  saveDashboard: () => void;
}

const useSaveDashboard = (): UseSaveDashboardState => {
  const { t } = useTranslation();
  const { dashboardId } = routerParams.useParams();

  const queryClient = useQueryClient();

  const dashboard = useAtomValue(dashboardAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => getDashboardEndpoint(dashboardId),
    method: Method.PATCH
  });

  const saveDashboard = (): void => {
    mutateAsync({
      payload: { panels: formatPanelsToAPI(dashboard.layout) }
    }).then(() => {
      showSuccessMessage(t(labelYourDashboardHasBeenSaved));
      switchPanelsEditionMode(false);
      queryClient.invalidateQueries({
        queryKey: [resource.dashboard, dashboardId]
      });
    });
  };

  return { saveDashboard };
};

export default useSaveDashboard;
