import { useTranslation } from 'react-i18next';
import { omit } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery, useSnackbar } from '@centreon/ui';

import { useCreateDashboard } from '../api/useCreateDashboard';
import {
  labelDashboardDuplicated,
  labelFailedToDuplicateDashboard
} from '../translatedLabels';
import { dashboardToDuplicateAtom } from '../atoms';
import { dashboardsEndpoint } from '../api/endpoints';
import { Dashboard } from '../api/models';

type UseDashboardForm = (name) => void;

const useDashboardDuplicate = (): UseDashboardForm => {
  const { t } = useTranslation();

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const dashboardToDuplicate = useAtomValue(dashboardToDuplicateAtom);

  const { mutate: duplicateDashboardMutation } = useCreateDashboard();

  const { data: dashboard } = useFetchQuery<Dashboard>({
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardToDuplicate?.id}`,
    getQueryKey: () => ['dashboardToDuplicate', dashboardToDuplicate?.id],
    queryOptions: {
      enabled: Boolean(dashboardToDuplicate),
      suspense: false
    }
  });

  const duplicateDashboard = (name): void => {
    const { panels, description, refresh } = dashboard as Dashboard;

    const panelsWithoutIds = panels?.map((panel) => omit(['id'], panel));

    const payload = {
      description,
      name
      // panels: panelsWithoutIds,
      // refresh
    };

    duplicateDashboardMutation(payload)
      .then((response) => {
        if (!response?.isError) {
          showSuccessMessage(t(labelDashboardDuplicated));
        }
      })
      .catch(() => showErrorMessage(t(labelFailedToDuplicateDashboard)));
  };

  return duplicateDashboard;
};

export { useDashboardDuplicate };
