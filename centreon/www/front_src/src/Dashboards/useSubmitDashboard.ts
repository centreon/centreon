import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { append, equals, findIndex, lensPath, propEq, set } from 'ramda';
import { useTranslation } from 'react-i18next';
import { FormikHelpers } from 'formik';

import {
  Method,
  useOptimisticListingMutation,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { DashboardResource, FormVariant } from '@centreon/ui/components';

import { Dashboard } from './models';
import { dashboardsEndpoint } from './api/endpoints';
import {
  closeDialogAtom,
  pageAtom,
  selectedDashboardAtom,
  selectedDashboardVariantAtom
} from './atoms';
import {
  labelDashboardCreated,
  labelDashboardUpdated,
  labelFailedToCreateDashboard,
  labelFailedToUpdateDashboard
} from './translatedLabels';

interface UseSubmitDashboardState {
  isMutating: boolean;
  submit: (
    dashboard: DashboardResource,
    formikHelpers: FormikHelpers<DashboardResource>
  ) => void;
}

const useSubmitDashboard = (): UseSubmitDashboardState => {
  const { t } = useTranslation();

  const [page, setPage] = useAtom(pageAtom);
  const selectedDashboardVariant = useAtomValue(selectedDashboardVariantAtom);
  const selectedDashboard = useAtomValue(selectedDashboardAtom);
  const closeDialog = useSetAtom(closeDialogAtom);

  const isUpdateVariant = equals(
    selectedDashboardVariant,
    'update' as FormVariant
  );

  const { applyListingMutation, rollBackListingMutation } =
    useOptimisticListingMutation();

  const { mutateAsync, isMutating } = useMutationQuery<Dashboard>({
    getEndpoint: () =>
      isUpdateVariant
        ? `${dashboardsEndpoint}/${selectedDashboard?.dashboard?.id}`
        : dashboardsEndpoint,
    method: isUpdateVariant ? Method.PUT : Method.POST,
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
        return applyListingMutation({
          getNewData: (previousListing) => {
            const index = findIndex(
              propEq('id', variables.id),
              previousListing.result
            );
            const newListing = {
              meta: previousListing.meta,
              result: isUpdateVariant
                ? set(lensPath([index]), variables, previousListing.result)
                : append(variables, previousListing.result)
            };

            return newListing;
          },
          page,
          queryKey: ['dashboards']
        });
      },
      onSuccess: () => {
        showSuccessMessage(
          t(isUpdateVariant ? labelDashboardUpdated : labelDashboardCreated)
        );
        closeDialog();
        setPage(1);
      },
      queryKeyToInvalidate: ['dashboards']
    }
  });

  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const displayErrorMessage = (): void => {
    showErrorMessage(
      t(
        isUpdateVariant
          ? labelFailedToUpdateDashboard
          : labelFailedToCreateDashboard
      )
    );
  };

  const submit = (values: DashboardResource, { setSubmitting }): void => {
    const normalizedValues: DashboardResource = {
      description: values.description || undefined,
      name: values.name
    };

    mutateAsync(normalizedValues).finally(() => setSubmitting(false));
  };

  return {
    isMutating,
    submit
  };
};

export default useSubmitDashboard;
