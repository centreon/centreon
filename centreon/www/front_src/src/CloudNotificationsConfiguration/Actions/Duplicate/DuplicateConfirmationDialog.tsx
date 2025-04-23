import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelDiscard,
  labelDuplicate,
  labelPleaseEnterNameForDuplicatedNotification
} from '../../translatedLabels';

import NotificationName from './NotificationName';
import useDuplicate from './useDuplicate';

const DuplicateConfirmationDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { closeDialog, isDialogOpen } = useDuplicate();

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  const disabled = !isValid || !dirty || isSubmitting;

  return (
    <ConfirmDialog
      confirmDisabled={disabled}
      labelCancel={t(labelDiscard)}
      labelConfirm={t(labelDuplicate)}
      labelTitle={t(labelPleaseEnterNameForDuplicatedNotification)}
      open={isDialogOpen}
      submitting={isSubmitting}
      onCancel={closeDialog}
      onConfirm={submitForm}
    >
      <NotificationName />
    </ConfirmDialog>
  );
};

export default DuplicateConfirmationDialog;
