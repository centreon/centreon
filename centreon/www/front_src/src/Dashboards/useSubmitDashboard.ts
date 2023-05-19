import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { FormikHelpers } from 'formik';

import {
  Method,
  ResponseError,
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

  const selectedDashboardVariant = useAtomValue(selectedDashboardVariantAtom);
  const selectedDashboard = useAtomValue(selectedDashboardAtom);
  const closeDialog = useSetAtom(closeDialogAtom);
  const setPage = useSetAtom(pageAtom);

  const isUpdateVariant = equals(
    selectedDashboardVariant,
    'update' as FormVariant
  );

  const { mutateAsync, isMutating } = useMutationQuery<Dashboard>({
    getEndpoint: () =>
      isUpdateVariant
        ? `${dashboardsEndpoint}/${selectedDashboard?.dashboard?.id}`
        : dashboardsEndpoint,
    method: isUpdateVariant ? Method.PUT : Method.POST
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

    mutateAsync(normalizedValues)
      .then((response) => {
        if ((response as ResponseError).isError) {
          displayErrorMessage();

          return;
        }
        showSuccessMessage(
          t(isUpdateVariant ? labelDashboardUpdated : labelDashboardCreated)
        );
        closeDialog();
        setPage(1);
      })
      .catch(displayErrorMessage)
      .finally(() => setSubmitting(false));
  };

  return {
    isMutating,
    submit
  };
};

export default useSubmitDashboard;
