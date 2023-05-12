import { useFormikContext } from 'formik';

import { Button } from '../../Button';

import {
  DashboardFormDataShape,
  DashboardFormProps,
  DashboardFormVariant
} from './models';
import { useStyles } from './DashboardForm.styles';

const FormActions =
  ({
    labels,
    onCancel,
    variant = DashboardFormVariant.Create
  }: Pick<DashboardFormProps, 'labels' | 'onCancel' | 'variant'>) =>
  (): JSX.Element => {
    const { classes } = useStyles();
    const { isSubmitting, dirty, isValid, submitForm } =
      useFormikContext<DashboardFormDataShape>();

    return (
      <div className={classes.actions}>
        <Button
          dataTestId={`${labels.actions?.submit[variant]}-dashboard-cancel`}
          disabled={isSubmitting}
          size="small"
          onClick={onCancel}
        >
          {labels.actions?.cancel}
        </Button>
        <Button
          dataTestId={`${labels.actions?.submit[variant]}-dashboard-confirm`}
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
