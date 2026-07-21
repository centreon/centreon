import { ConfirmDialog } from '@centreon/ui';

import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

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
      onCancel={closeDialog}
      onConfirm={submitForm}
      open={isDialogOpen}
      submitting={isSubmitting}
    >
      <NotificationName />
    </ConfirmDialog>
  );
};

export default DuplicateConfirmationDialog;
