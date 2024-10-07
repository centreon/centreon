import { useTheme } from '@mui/material';
import { useQueryClient } from '@tanstack/react-query';
import { toBlob } from 'html-to-image';
import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  getDashboardEndpoint,
  mediasEndpoint
} from '../../../api/endpoints';

import { resource } from '../../../api/models';
import { dashboardAtom, switchPanelsEditionModeDerivedAtom, thumbnailAtom } from '../atoms';
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
  const theme = useTheme();

  const dashboard = useAtomValue(dashboardAtom);

  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const thumbnail = useAtomValue(thumbnailAtom)

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => getDashboardEndpoint(dashboardId),
    method: Method.PATCH
  });
  const { mutateAsync: mutateMedias } = useMutationQuery({
    getEndpoint: () =>
      thumbnail
        ? `${mediasEndpoint}/${thumbnail?.id}/content`
        : mediasEndpoint,
    method: Method.POST
  });

  const saveDashboard = (): void => {
    const node = document.querySelector('.react-grid-layout') as HTMLElement;

    toBlob(node, {
      backgroundColor: theme.palette.background.default,
      height: 360
    })
      .then((blob) => {
        if (!blob) {
          return;
        }

        const formData = new FormData();
        formData.append('directory', 'dashboards');
        formData.append('data', blob, `dashboard-${dashboardId}.png`);

        return mutateMedias({ payload: formData }).then((res) => {
          const response = thumbnail
            ? res
            : res?.result?.[0];

          const newThumbnail = {
            name: response?.filename,
            id: response?.id,
            directory: response?.directory
          };

          return mutateAsync({
            payload: {
              panels: formatPanelsToAPI(dashboard.layout),
              thumbnail: newThumbnail
            }
          });
        });
      })
      .then(() => {
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
