import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelCancel,
  labelDuplicate,
  labelEnterNameForDuplicatedRule
} from '../../translatedLabels';

import useDuplicate from './useDuplicate';
import ResourceAccessRuleName from './ResourceAccessRuleName';

const DuplicateConfirmationDialog = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { closeDialog, isDialogOpen } = useDuplicate();

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  const disabled = !isValid || !dirty || isSubmitting;

  return (
    <ConfirmDialog
      confirmDisabled={disabled}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDuplicate)}
      labelTitle={t(labelEnterNameForDuplicatedRule)}
      open={isDialogOpen}
      submitting={isSubmitting}
      onCancel={closeDialog}
      onConfirm={submitForm}
    >
      <ResourceAccessRuleName />
    </ConfirmDialog>
  );
};

export default DuplicateConfirmationDialog;
