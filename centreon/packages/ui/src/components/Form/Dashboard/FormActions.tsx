import { useFormikContext } from 'formik';

import { Button } from '../../Button';

import { DashboardFormDataShape, DashboardFormProps } from './models';
import { useStyles } from './DashboardForm.styles';

const FormActions = ({
  labels,
  onCancel,
  variant = 'create'
}: Pick<
  DashboardFormProps,
  'labels' | 'onCancel' | 'variant'
>): JSX.Element => {
  const { classes } = useStyles();
  const { isSubmitting, dirty, isValid, submitForm } =
    useFormikContext<DashboardFormDataShape>();

  return (
    <div className={classes.actions}>
      <Button disabled={isSubmitting} size="small" onClick={onCancel}>
        {labels.actions?.cancel}
      </Button>
      <Button
        disabled={isSubmitting || !dirty || !isValid}
        size="small"
        type="submit"
        onClick={submitForm}
      >
        {labels.actions?.submit[variant]}
      </Button>
    </div>
  );
};

export { FormActions };
