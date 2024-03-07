import { useTranslation } from 'react-i18next';
import { map, omit } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery, useSnackbar } from '@centreon/ui';

import { useCreateDashboard } from '../api/useCreateDashboard';
import {
  labelDashboardDuplicated,
  labelFailedToDuplicateDashboard
} from '../translatedLabels';
import { dashboardToDuplicateAtom } from '../atoms';
import { useUpdateDashboard } from '../api/useUpdateDashboard';
import { dashboardsEndpoint } from '../api/endpoints';

type UseDashboardForm = (name) => void;

const useDashboardDuplicate = (): UseDashboardForm => {
  const { t } = useTranslation();
  const dashboardToDuplicate = useAtomValue(dashboardToDuplicateAtom);

  const { mutate: duplicateDashboardMutation } = useCreateDashboard();
  const { mutate: setPanelToDuplicatedDashboard } = useUpdateDashboard();

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { data: dashboard } = useFetchQuery({
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardToDuplicate?.id}`,
    getQueryKey: () => ['dashboardToDuplicate', dashboardToDuplicate?.id],
    queryOptions: {
      enabled: Boolean(dashboardToDuplicate),
      suspense: false
    }
  });

  const duplicateDashboard = (name): void => {
    const panels = map((panel) => omit(['id'], panel), dashboard?.panels);

    const payload = {
      description: dashboard?.description,
      name
      // panels,
      // refresh: dashboard?.refresh
    };

    duplicateDashboardMutation(payload)
      .then(() => {
        showSuccessMessage(t(labelDashboardDuplicated));
      })
      // .then((response) => {
      //   setPanelToDuplicatedDashboard({
      //     id: response?.id,
      //     panels,
      //     refresh: dashboard?.refresh
      //   });
      //   showSuccessMessage(t(labelDashboardDuplicated));
      // })
      .catch(() => showErrorMessage(t(labelFailedToDuplicateDashboard)));
  };

  return duplicateDashboard;
};

export { useDashboardDuplicate };
