import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Button } from '../../Button';
import SaveButton from '../../../Button/Save';

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
    const { t } = useTranslation();
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
          {t(labels.actions?.cancel)}
        </Button>
        <SaveButton
          dataTestId={`${labels.actions?.submit[variant]}-dashboard-confirm`}
          disabled={isSubmitting || !dirty || !isValid}
          labelSave={t(labels.actions?.submit[variant]) as string}
          loading={isSubmitting}
          size="small"
          onClick={submitForm}
        />
      </div>
    );
  };

export { FormActions };
