import React, { useCallback, useMemo } from 'react';
import { useStyles } from './DashboardForm.styles';
import { InputType } from '../../../Form/Inputs/models';
import { Form, FormProps } from '../../../Form';
import * as Yup from 'yup';
import { Button } from '../../Button';
import { useFormikContext } from 'formik';


type DashboardFormProps = {
  variant?: 'create' | 'update';

  labels: DashboardFormLabels;

  resource?: any; // TODO type

  onSubmit?: FormProps<DashboardFormDataShape>['submit'];
  onCancel?: () => void;

  isSubmitting?: boolean;
}

type DashboardFormLabels = {
  title: {
    create: string;
    update: string;
  },
  description?: {},
  entity: {
    name: string;
    description: string;
  },
  actions: {
    submit: {
      create: string;
      update: string;
    },
    cancel: string;
  }
}

type DashboardFormDataShape = {
  name: string;
  description?: string;
}

const DashboardForm: React.FC<DashboardFormProps> = ({
  variant = 'create',
  resource,
  labels,
  onSubmit,
  onCancel,
  isSubmitting = false
}: DashboardFormProps): JSX.Element => {
  const {classes} = useStyles();

  const formProps = useMemo<FormProps<DashboardFormDataShape>>(() => ({
    initialValues: resource ?? {},
    inputs: [
      {
        fieldName: 'name',
        label: labels?.entity?.name,
        type: InputType.Text,
        required: true,
        group: 'main'
      },
      {
        fieldName: 'description',
        label: labels?.entity?.description,
        type: InputType.Text,
        text: {
          multilineRows: 3
        },
        group: 'main'
      }
    ],
    validationSchema: Yup.object().shape({
      name: Yup.string()
      .label(labels?.entity?.name)
      .min(3, p => `${p.label} must be at least ${p.min} characters`)
      .max(50, p => `${p.label} must be at most ${p.max} characters`)
      .required(p => `${p.label} is required`),
      description: Yup.string()
      .label(labels?.entity?.description)
      .max(180, p => `${p.label} must be at most ${p.max} characters`)
      .optional()
    }),
    submit: (values, bag) => onSubmit?.(values, bag)

  }), [resource, labels, onSubmit]);


  const FormActions = useCallback((): JSX.Element => {
    const {isSubmitting, dirty, isValid, submitForm} =
      useFormikContext<DashboardFormDataShape>();


    return (
      <div className={classes.actions}>
        <Button
          variant="secondary"
          size="small"
          onClick={() => onCancel?.()}
          disabled={isSubmitting}
        >
          {labels.actions?.cancel}
        </Button>
        <Button
          variant="primary"
          size="small"
          type="submit"
          disabled={isSubmitting || !dirty || !isValid}
          onClick={e => submitForm()}
        >
          {labels.actions?.submit[variant]}
        </Button>
      </div>
    );
  }, [classes, onCancel, labels, variant]);


  return (
    <div className={classes.dashboardForm}>
      <h2>{labels?.title[variant]}</h2>
      <Form<DashboardFormDataShape>
        {...formProps}
        Buttons={FormActions}
      />
    </div>
  );
};

export { DashboardForm };