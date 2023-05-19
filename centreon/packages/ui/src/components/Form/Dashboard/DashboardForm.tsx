import React, { useCallback, useMemo } from 'react';

import * as Yup from 'yup';
import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { InputType } from '../../../Form/Inputs/models';
import { Form, FormProps } from '../../../Form';
import { Button } from '../../Button';

import { useStyles } from './DashboardForm.styles';
import {
  labelCharacters,
  labelMustBeAtLeast,
  labelMustBeMost,
  labelRequired
} from './translatedLabels';

export type DashboardFormProps = {
  labels: DashboardFormLabels;
  onCancel?: () => void;
  onSubmit?: FormProps<DashboardResource>['submit'];
  resource?: DashboardResource;
  variant?: 'create' | 'update';
};

export type DashboardFormLabels = {
  actions: {
    cancel: string;
    submit: Record<Required<DashboardFormProps>['variant'], string>;
  };
  entity: {
    description: string;
    name: string;
  };
  title: Record<Required<DashboardFormProps>['variant'], string>;
};

export type DashboardResource = {
  description?: string | null;
  name: string;
};

const DashboardForm: React.FC<DashboardFormProps> = ({
  variant = 'create',
  resource,
  labels,
  onSubmit,
  onCancel
}: DashboardFormProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const formProps = useMemo<FormProps<DashboardResource>>(
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
          .max(
            180,
            (p) =>
              `${p.label} ${t(labelMustBeMost)} ${p.max} ${labelCharacters}`
          )
          .optional(),
        name: Yup.string()
          .label(labels?.entity?.name)
          .min(
            3,
            (p) =>
              `${p.label} ${t(labelMustBeAtLeast)} ${p.min} ${labelCharacters}`
          )
          .max(
            50,
            (p) =>
              `${p.label} ${t(labelMustBeMost)} ${p.max} ${labelCharacters}`
          )
          .required(t(labelRequired) as string)
      })
    }),
    [resource, labels, onSubmit]
  );

  const FormActions = useCallback<React.FC>((): JSX.Element => {
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
          {labels.actions?.cancel}
        </Button>
        <Button
          aria-label="submit"
          disabled={isSubmitting || !dirty || !isValid}
          size="small"
          type="submit"
          variant="primary"
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
      <Form<DashboardResource> {...formProps} Buttons={FormActions} />
    </div>
  );
};

export { DashboardForm };
