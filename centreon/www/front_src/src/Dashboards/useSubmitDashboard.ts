import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { FormikHelpers } from 'formik';

import {
  DashboardFormDataShape,
  DashboardFormVariant,
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { Dashboard } from './models';
import { dashboardsEndpoint } from './api/endpoints';
import { closeDialogAtom, selectedDashboardVariantAtom } from './atoms';
import {
  labelDashboardCreated,
  labelDashboardUpdated,
  labelFailedToCreateDashboard,
  labelFailedToUpdateDashboard
} from './translatedLabels';

interface UseSubmitDashboardState {
  isMutating: boolean;
  submit: (
    dashboard: DashboardFormDataShape,
    formikHelpers: FormikHelpers<DashboardFormDataShape>
  ) => void;
}

const useSubmitDashboard = (): UseSubmitDashboardState => {
  const { t } = useTranslation();

  const selectedDashboardVariant = useAtomValue(selectedDashboardVariantAtom);
  const closeDialog = useSetAtom(closeDialogAtom);

  const isUpdateVariant = equals(
    selectedDashboardVariant,
    DashboardFormVariant.Update
  );

  const { mutateAsync, isMutating } = useMutationQuery<Dashboard>({
    getEndpoint: () => dashboardsEndpoint,
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

  const submit = (values: DashboardFormDataShape, { setSubmitting }): void => {
    const normalizedValues: DashboardFormDataShape = {
      description: values.description || null,
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
