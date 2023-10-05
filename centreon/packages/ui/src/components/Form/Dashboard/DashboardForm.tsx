import { ReactElement, useCallback, useMemo } from 'react';

import * as Yup from 'yup';
import { useTranslation } from 'react-i18next';

import { InputType } from '../../../Form/Inputs/models';
import { Form, FormProps } from '../../../Form';
import { FormVariant } from '../Form.models';
import { FormActions, FormActionsProps } from '../FormActions';

import { useStyles } from './DashboardForm.styles';
import {
  labelCharacters,
  labelMustBeAtLeast,
  labelMustBeMost,
  labelRequired
} from './translatedLabels';
import { DashboardResource } from './Dashboard.resource';
import GlobalRefreshFieldOption from './GlobalRefreshFieldOption';

export type DashboardFormProps = {
  labels: DashboardFormLabels;
  onSubmit?: FormProps<DashboardResource>['submit'];
  resource?: DashboardResource;
  showRefreshIntervalFields?: boolean;
  variant?: FormVariant;
} & Pick<FormActionsProps, 'onCancel'>;

export type DashboardFormLabels = {
  actions: FormActionsProps['labels'];
  entity: Required<DashboardResource>;
};

const DashboardForm = ({
  variant = 'create',
  resource,
  labels,
  onSubmit,
  onCancel,
  showRefreshIntervalFields
}: DashboardFormProps): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const formProps = useMemo<FormProps<DashboardResource>>(
    () => ({
      initialValues: resource ?? { description: null, name: '' },
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
          label: labels?.entity?.description || '',
          text: {
            multilineRows: 3
          },
          type: InputType.Text
        },
        {
          fieldName: 'refresh.type',
          group: 'main',
          hideInput: () => !showRefreshIntervalFields,
          label: labels?.entity?.globalRefreshInterval?.title,
          radio: {
            options: [
              {
                label: <GlobalRefreshFieldOption />,
                value: 'global'
              },
              {
                label: labels?.entity?.globalRefreshInterval?.manual,
                value: 'manual'
              }
            ],
            row: false
          },
          type: InputType.Radio
        }
      ],
      submit: (values, bag) => onSubmit?.(values, bag),
      validationSchema: Yup.object().shape({
        description: Yup.string()
          .label(labels?.entity?.description || '')
          .max(
            180,
            (p) =>
              `${p.label} ${t(labelMustBeMost)} ${p.max} ${t(labelCharacters)}`
          )
          .nullable(),
        globalRefreshInterval: Yup.object().shape({
          interval: Yup.number().when('type', {
            is: 'global',
            otherwise: Yup.number().nullable(),
            then: Yup.number()
              .min(1, ({ min }) => t(labelMustBeAtLeast, { min }))
              .required(t(labelRequired) as string)
          }),
          type: Yup.string()
        }),
        name: Yup.string()
          .label(labels?.entity?.name)
          .min(3, ({ min, label }) => t(labelMustBeAtLeast, { label, min }))
          .max(50, ({ max, label }) => t(labelMustBeMost, { label, max }))
          .required(t(labelRequired) as string)
      })
    }),
    [resource, labels, onSubmit]
  );

  const Actions = useCallback(
    () => (
      <FormActions<DashboardResource>
        labels={labels?.actions}
        variant={variant}
        onCancel={onCancel}
      />
    ),
    [labels, onCancel, variant]
  );

  return (
    <div className={classes.dashboardForm}>
      <Form<DashboardResource> {...formProps} Buttons={Actions} />
    </div>
  );
};

export { DashboardForm };
