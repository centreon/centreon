import React, { useCallback, useMemo } from 'react';

import * as Yup from 'yup';
import { useFormikContext } from 'formik';

import { InputType } from '../../../Form/Inputs/models';
import { Form, FormProps } from '../../../Form';
import { Button } from '../../Button';

import { useStyles } from './DashboardForm.styles';
import { DashboardFormDataShape, DashboardFormProps } from './models';

const DashboardForm: React.FC<DashboardFormProps> = ({
  variant = 'create',
  resource,
  labels,
  onSubmit,
  onCancel
}: DashboardFormProps): JSX.Element => {
  const { classes } = useStyles();

  const formProps = useMemo<FormProps<DashboardFormDataShape>>(
    () => ({
      initialValues: resource ?? { name: '' },
      inputs: [
        {
          fieldName: 'name',
          group: 'main',
          label: labels?.entity?.name,
          required: true,
          type: InputType.Text
        },
        {
          fieldName: 'description',
          group: 'main',
          label: labels?.entity?.description,
          text: {
            multilineRows: 3
          },
          type: InputType.Text
        }
      ],
      submit: (values, bag) => onSubmit?.(values, bag),
      validationSchema: Yup.object().shape({
        description: Yup.string()
          .label(labels?.entity?.description)
          .max(180, (p) => `${p.label} must be at most ${p.max} characters`)
          .optional(),
        name: Yup.string()
          .label(labels?.entity?.name)
          .min(3, (p) => `${p.label} must be at least ${p.min} characters`)
          .max(50, (p) => `${p.label} must be at most ${p.max} characters`)
          .required((p) => `${p.label} is required`)
      })
    }),
    [resource, labels, onSubmit]
  );

  const FormActions = useCallback((): JSX.Element => {
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
  }, [classes, onCancel, labels, variant]);

  return (
    <div className={classes.dashboardForm}>
      <h2>{labels?.title[variant]}</h2>
      <Form<DashboardFormDataShape> {...formProps} Buttons={FormActions} />
    </div>
  );
};

export { DashboardForm };
