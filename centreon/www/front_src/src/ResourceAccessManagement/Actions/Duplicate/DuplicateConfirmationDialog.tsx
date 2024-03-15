import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';
import { path } from 'ramda';

import { ConfirmDialog, TextField } from '@centreon/ui';

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
    dirty,
    errors,
    handleBlur,
    isSubmitting,
    isValid,
    setFieldTouched,
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

  const disabled = !isValid || !dirty || isSubmitting;

  const onCancel = (): void => {
    setFieldValue('name', undefined);
    setFieldTouched('name', false, false);
    closeDialog();
  };

  return (
    <ConfirmDialog
      confirmDisabled={disabled}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDuplicate)}
      labelTitle={t(labelEnterNameForDuplicatedRule)}
      open={isDialogOpen}
      submitting={isSubmitting}
      onCancel={onCancel}
      onConfirm={submitForm}
    >
      <TextField
        required
        ariaLabel={labelResourceAccessRuleName}
        dataTestId="New resource access rule name"
        error={error as string | undefined}
        label={t(labelName) as string}
        name="name"
        value={ruleName}
        onBlur={handleBlur('name')}
        onChange={handleChange}
      />
    </ConfirmDialog>
  );
};

export default DuplicateConfirmationDialog;
