import React, { ReactElement } from 'react';

import { useFormikContext } from 'formik';

import { Button } from '../../Button';
import { FormVariant } from '../Form.models';

import { useStyles } from './DashboardForm.styles';
import { DashboardResource } from './Dashboard.resource';

export type DashboardFormActionsProps = {
  labels: DashboardFormActionsLabels;
  onCancel: () => void;
  variant: FormVariant;
};

export type DashboardFormActionsLabels = {
  cancel: string;
  submit: Record<FormVariant, string>;
};

const DashboardFormActions = ({
  labels,
  onCancel,
  variant
}: DashboardFormActionsProps): ReactElement => {
  const { classes } = useStyles();
  const { isSubmitting, dirty, isValid, submitForm } =
    useFormikContext<DashboardResource>();

  return (
    <div className={classes.actions}>
      <Button
        aria-label="cancel"
        disabled={isSubmitting}
        size="small"
        variant="secondary"
        onClick={() => onCancel?.()}
      >
        {labels.cancel}
      </Button>
      <Button
        aria-label="submit"
        disabled={isSubmitting || !dirty || !isValid}
        size="small"
        type="submit"
        variant="primary"
        onClick={submitForm}
      >
        {labels.submit[variant]}
      </Button>
    </div>
  );
};

export { DashboardFormActions };
