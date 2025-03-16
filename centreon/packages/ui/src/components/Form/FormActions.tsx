import { ReactElement } from 'react';

import { useFormikContext } from 'formik';

import { Button } from '../Button';

import { FormVariant } from './Form.models';
import { useStyles } from './Form.styles';

export type FormActionsProps = {
  enableSubmitWhenNotDirty?: boolean;
  labels: FormActionsLabels;
  onCancel: () => void;
  variant: FormVariant;
  isSubmitButtonVisible?: boolean;
};

export type FormActionsLabels = {
  cancel: string;
  submit: Record<FormVariant, string>;
};

const FormActions = <TResource extends object>({
  labels,
  onCancel,
  variant,
  enableSubmitWhenNotDirty,
  isSubmitButtonVisible = true
}: FormActionsProps): ReactElement => {
  const { classes } = useStyles();
  const { isSubmitting, dirty, isValid, submitForm } =
    useFormikContext<TResource>();

  const isSubmitDisabled =
    isSubmitting || (!dirty && !enableSubmitWhenNotDirty) || !isValid;

  return (
    <div className={classes.actions}>
      <Button
        aria-label={labels.cancel}
        data-testid="cancel"
        disabled={isSubmitting}
        size="medium"
        variant="secondary"
        onClick={() => onCancel?.()}
      >
        {labels.cancel}
      </Button>
      {isSubmitButtonVisible && (
        <Button
          aria-label={labels.submit[variant]}
          data-testid="submit"
          disabled={isSubmitDisabled}
          size="medium"
          type="submit"
          variant="primary"
          onClick={submitForm}
        >
          {labels.submit[variant]}
        </Button>
      )}
    </div>
  );
};

export { FormActions };
