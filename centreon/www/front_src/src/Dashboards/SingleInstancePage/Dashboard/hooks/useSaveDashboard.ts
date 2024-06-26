import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { toJpeg, toPng } from 'html-to-image';

import { useTheme } from '@mui/material';

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

const filterNode = (node): boolean => {
  if (node instanceof Text) {
    return true;
  }

  return (
    [
      'div',
      'span',
      'p',
      'i',
      'strong',
      'main',
      'aside',
      'article',
      'pre',
      'code',
      'time',
      'address',
      'header',
      'footer',
      'svg'
    ].includes(node.tagName.toLowerCase()) || /^h[123456]$/i.test(node.tagName)
  );
};

const useSaveDashboard = (): UseSaveDashboardState => {
  const { t } = useTranslation();
  const { dashboardId } = routerParams.useParams();

  const queryClient = useQueryClient();
  const theme = useTheme();

  const dashboard = useAtomValue(dashboardAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    baseEndpoint: 'http://localhost:3001/centreon/',
    getEndpoint: () => getDashboardEndpoint(dashboardId),
    method: Method.PATCH
  });

  const saveDashboard = (): void => {
    const node = document.querySelector('.react-grid-layout') as Element;
    toJpeg(node, {
      backgroundColor: theme.palette.background.default,
      quality: 0.3
    }).then((data) => {
      mutateAsync({
        payload: {
          panels: formatPanelsToAPI(dashboard.layout),
          thumbnail: data
        }
      }).then(() => {
        showSuccessMessage(t(labelYourDashboardHasBeenSaved));
        switchPanelsEditionMode(false);
        queryClient.invalidateQueries({
          queryKey: [resource.dashboard, dashboardId]
        });
      });
    });
  };

  return { saveDashboard };
};

export default useSaveDashboard;
