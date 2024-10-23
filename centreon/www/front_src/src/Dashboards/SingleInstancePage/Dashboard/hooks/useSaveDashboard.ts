import { useTheme } from '@mui/material';
import { useQueryClient } from '@tanstack/react-query';
import { toBlob } from 'html-to-image';
import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { getDashboardEndpoint } from '../../../api/endpoints';

import { resource } from '../../../api/models';
import {
  dashboardAtom,
  switchPanelsEditionModeDerivedAtom
} from '../atoms';
import { Panel, PanelDetailsToAPI } from '../models';
import { labelYourDashboardHasBeenSaved } from '../translatedLabels';

import { routerParams } from './useDashboardDetails';
import { isEmpty, isNil } from 'ramda';

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

const dataToFormData = ({ panels, formData }): void => {
    if (isEmpty(panels) || isNil(panels)) {
      formData.append('panels[]', "");

      return;
    }
  
    panels.forEach((panel, index) => {
      formData.append(`panels[${index}][name]`, panel.name);
      formData.append(`panels[${index}][widget_type]`, panel.widget_type);
  
      formData.append(`panels[${index}][layout][x]`, panel.layout.x);
      formData.append(`panels[${index}][layout][y]`, panel.layout.y);
      formData.append(`panels[${index}][layout][width]`, panel.layout.width);
      formData.append(`panels[${index}][layout][height]`, panel.layout.height);
      formData.append(
        `panels[${index}][layout][min_width]`,
        panel.layout.min_width
      );
      formData.append(
        `panels[${index}][layout][min_height]`,
        panel.layout.min_height
      );
  
      formData.append(
        `panels[${index}][widget_settings]`,
        JSON.stringify(panel.widget_settings)
      );
    });
  }
  
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

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => getDashboardEndpoint(dashboardId),
    method: Method.POST
  });

  const saveDashboard = (): void => {
    const formData = new FormData();

    dataToFormData(formatPanelsToAPI(dashboard.layout), formData);

    const node = document.querySelector('.react-grid-layout') as HTMLElement;

    toBlob(node, {
      backgroundColor: theme.palette.background.default,
      height: 360
    }).then((blob) => {
      if (!blob) {
        return;
      }

      formData.append('thumbnail_data', blob, `dashboard-${dashboardId}.png`);
      formData.append('thumbnail[directory]', 'dashboards');
      formData.append('thumbnail[name]', `dashboard-${dashboardId}.png`);
    }).finally(() => {
      mutateAsync({
        payload: formData
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
