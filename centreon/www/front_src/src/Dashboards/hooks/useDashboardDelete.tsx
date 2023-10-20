import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { Dashboard } from '../api/models';
import { useDeleteDashboard } from '../api/useDeleteDashboard';
import {
  labelDashboardDeleted,
  labelFailedToDeleteDashboard
} from '../translatedLabels';

type UseDashboardForm = (dashboard: Dashboard) => () => void;

const useDashboardDelete = (): UseDashboardForm => {
  const { t } = useTranslation();
  const { mutate: deleteDashboardMutation } = useDeleteDashboard();

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const deleteDashboard = (dashboard: Dashboard) => (): void => {
    deleteDashboardMutation(dashboard)
      .then(() => showSuccessMessage(t(labelDashboardDeleted)))
      .catch(() => showErrorMessage(t(labelFailedToDeleteDashboard)));
  };

  return deleteDashboard;
};

export { useDashboardDelete };
