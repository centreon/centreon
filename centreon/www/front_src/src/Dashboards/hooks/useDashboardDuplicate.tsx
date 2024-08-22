import { useAtomValue } from 'jotai';
import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';
import { Dashboard } from '../api/models';
import { useCreateDashboard } from '../api/useCreateDashboard';
import { dashboardToDuplicateAtom } from '../atoms';
import {
  labelDashboardDuplicated,
  labelFailedToDuplicateDashboard
} from '../translatedLabels';

type UseDashboardForm = (name) => void;

const useDashboardDuplicate = (): UseDashboardForm => {
  const { t } = useTranslation();

  const dashboardToDuplicate = useAtomValue(dashboardToDuplicateAtom);

  const labels = {
    labelFailure: t(labelFailedToDuplicateDashboard),
    labelSuccess: t(labelDashboardDuplicated)
  };

  const { mutate: duplicateDashboardMutation } = useCreateDashboard({
    labels
  });

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
      name,
      panels: panelsWithoutIds,
      refresh
    };

    duplicateDashboardMutation(payload);
  };

  return duplicateDashboard;
};

export { useDashboardDuplicate };
