import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { dashboardsEndpoint } from './api/endpoints';
import { deleteDialogStateAtom, pageAtom } from './atoms';
import {
  labelDashboardDeleted,
  labelFailedToDeleteDashboard
} from './translatedLabels';
import { Dashboard } from './models';

interface UseRemoveDashboardState {
  isMutating: boolean;
  remove: (dashboard: Dashboard) => void;
}

const useRemoveDashboard = (): UseRemoveDashboardState => {
  const { t } = useTranslation();

  const [page, setPage] = useAtom(pageAtom);
  const setDeleteDialogState = useSetAtom(deleteDialogStateAtom);

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: (payload) => `${dashboardsEndpoint}/${payload?.id}`,
    method: Method.DELETE
  });

  const queryClient = useQueryClient();

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const displayErrorMessage = (): void => {
    showErrorMessage(t(labelFailedToDeleteDashboard));
  };

  const remove = (values: Dashboard): void => {
    mutateAsync(values)
      .then((response) => {
        if ((response as ResponseError).isError) {
          displayErrorMessage();

          return;
        }
        showSuccessMessage(t(labelDashboardDeleted));
        setDeleteDialogState({ item: null, open: false });
        setPage(1);
        queryClient.invalidateQueries({
          queryKey: ['dashboards', page]
        });
      })
      .catch(displayErrorMessage);
  };

  return {
    isMutating,
    remove
  };
};

export default useRemoveDashboard;
