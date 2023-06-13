import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { propEq, reject } from 'ramda';

import {
  Method,
  useOptimisticListingMutation,
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

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { applyListingMutation, rollBackListingMutation } =
    useOptimisticListingMutation();

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: (payload) => `${dashboardsEndpoint}/${payload?.id}`,
    method: Method.DELETE,
    optimisticUI: {
      onError: (_, __, context) => {
        displayErrorMessage();
        rollBackListingMutation({
          context,
          page,
          queryKey: ['dashboards']
        });
      },
      onMutate: (variables) => {
        showSuccessMessage(t(labelDashboardDeleted));
        setDeleteDialogState({ item: null, open: false });
        setPage(1);

        return applyListingMutation({
          getNewData: (previousListing) => {
            const newListing = {
              meta: previousListing.meta,
              result: reject(propEq('id', variables.id), previousListing.result)
            };

            return newListing;
          },
          page,
          queryKey: ['dashboards']
        });
      },
      queryKeyToInvalidate: ['dashboards']
    }
  });

  const displayErrorMessage = (): void => {
    showErrorMessage(t(labelFailedToDeleteDashboard));
  };

  const remove = (values: Dashboard): void => {
    mutateAsync(values);
  };

  return {
    isMutating,
    remove
  };
};

export default useRemoveDashboard;
