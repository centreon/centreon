import React, { useMemo } from 'react';

import * as Yup from 'yup';
import { useTranslation } from 'react-i18next';

import { InputType } from '../../../Form/Inputs/models';
import { Form, FormProps } from '../../../Form';

import { useStyles } from './DashboardForm.styles';
import {
  DashboardFormDataShape,
  DashboardFormProps,
  DashboardFormVariant
} from './models';
import {
  labelCharacters,
  labelMustBeAtLeast,
  labelMustBeMost,
  labelRequired
} from './translatedLabels';
import { FormActions } from './FormActions';

const DashboardForm: React.FC<DashboardFormProps> = ({
  variant = DashboardFormVariant.Create,
  resource,
  labels,
  onSubmit,
  onCancel
}: DashboardFormProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

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

  return (
    <div className={classes.dashboardForm}>
      <h2>{labels?.title[variant]}</h2>
      <Form<DashboardFormDataShape>
        {...formProps}
        Buttons={FormActions({
          labels,
          onCancel,
          variant
        })}
      />
    </div>
  );
};

export { DashboardForm };
