import { ReactElement, useCallback, useMemo } from 'react';

import { string, object } from 'yup';
import { useTranslation } from 'react-i18next';

import { Form, FormProps, InputType } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import {
  labelCharacters,
  labelMustBeAtLeast,
  labelMustBeMost,
  labelRequired
} from '../translatedLabels';

import { useFormStyles } from './useModalStyles';

import {
  FormActions,
  FormActionsProps
} from 'packages/ui/src/components/Form/FormActions';

export type ConnectorsProperties = {
  description?: string | null;
  globalRefreshInterval?: {
    global: string;
    manual: string;
    title: string;
  };
  name: string;
};

export type DashboardFormProps = {
  labels: DashboardFormLabels;
  onSubmit?: FormProps<ConnectorsProperties>['submit'];
  resource?: ConnectorsProperties;
  variant?: FormVariant;
} & Pick<FormActionsProps, 'onCancel'>;

export type DashboardFormLabels = {
  actions: FormActionsProps['labels'];
  entity: Required<ConnectorsProperties>;
};

const ConnectorsForm = ({
  variant = 'create',
  resource,
  labels,
  onSubmit,
  onCancel
}: DashboardFormProps): ReactElement => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const formProps = useMemo<FormProps<ConnectorsProperties>>(
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
        }
      ],
      submit: (values, bag) => onSubmit?.(values, bag),
      validationSchema: object({
        description: string()
          .label(labels?.entity?.description || '')
          .max(
            180,
            (p) =>
              `${p.label} ${t(labelMustBeMost)} ${p.max} ${t(labelCharacters)}`
          )
          .nullable(),
        name: string()
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
      <FormActions<ConnectorsProperties>
        labels={labels?.actions}
        variant={variant}
        onCancel={onCancel}
      />
    ),
    [labels, onCancel, variant]
  );

  return (
    <div className={classes.form}>
      <Form<ConnectorsProperties> {...formProps} Buttons={Actions} />
    </div>
  );
};

export default ConnectorsForm;
