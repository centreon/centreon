import { ConfirmDialog, TextField } from '@centreon/ui';

import { FormikValues, useFormikContext } from 'formik';
import { path } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelDuplicate,
  labelEnterNameForDuplicatedRule,
  labelName,
  labelResourceAccessRuleName
} from '../../translatedLabels';
import useDuplicate from './useDuplicate';

const DuplicateConfirmationDialog = (): React.JSX.Element => {
  const { t } = useTranslation();
  const { closeDialog, isDialogOpen } = useDuplicate();

  const {
    errors,
    handleBlur,
    isSubmitting,
    isValid,
    setFieldValue,
    submitForm,
    touched,
    values: { name: ruleName }
  } = useFormikContext<FormikValues>();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const { value } = event.target;
    setFieldValue('name', value);
  };

  const error = path(['name'], touched) ? path(['name'], errors) : undefined;

  const disabled = !isValid || isSubmitting;

  const onCancel = (): void => {
    closeDialog();
  };

  return (
    <ConfirmDialog
      confirmDisabled={disabled}
      dialogContentTextProps={{ component: 'div' }}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDuplicate)}
      labelTitle={t(labelEnterNameForDuplicatedRule)}
      onCancel={onCancel}
      onConfirm={submitForm}
      open={isDialogOpen}
      submitting={isSubmitting}
    >
      <TextField
        ariaLabel={labelResourceAccessRuleName}
        dataTestId="New resource access rule name"
        error={error as string | undefined}
        label={t(labelName) as string}
        name="name"
        onBlur={handleBlur('name')}
        onChange={handleChange}
        required
        sx={{ width: '100%' }}
        value={ruleName}
      />
    </ConfirmDialog>
  );
};

export default DuplicateConfirmationDialog;
