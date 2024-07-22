import { ReactElement, useCallback, useMemo } from 'react';

import { string, object } from 'yup';
import { useTranslation } from 'react-i18next';

import { Form, FormProps, InputType } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import {
  labelCancel,
  labelCharacters,
  labelCreate,
  labelDescription,
  labelMustBeAtLeast,
  labelMustBeMost,
  labelName,
  labelParameters,
  labelPollers,
  labelPort,
  labelRequired,
  labelSelectType,
  labelType,
  labelUpdate,
  labelValue
} from '../translatedLabels';

import { useFormStyles } from './useModalStyles';
import Parameters from './Parameters/Parameters';

import {
  FormActions,
  FormActionsProps
} from 'packages/ui/src/components/Form/FormActions';

export type ConnectorsProperties = {
  description?: string | null;
  name: string;
  parameters;
  pollers;
  port: { name: string; value: number };
  type;
};

export type DashboardFormProps = {
  onSubmit?: FormProps<ConnectorsProperties>['submit'];
  resource?: ConnectorsProperties;
  variant?: FormVariant;
} & Pick<FormActionsProps, 'onCancel'>;

export type ConnectorFormLabels = {
  actions: FormActionsProps['labels'];
  entity;
};

const ConnectorsForm = ({
  variant = 'create',
  resource,
  onSubmit,
  onCancel
}: DashboardFormProps): ReactElement => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelCreate),
      update: t(labelUpdate)
    }
  };

  const formProps = useMemo<FormProps<ConnectorsProperties>>(
    () => ({
      initialValues: resource ?? {
        description: null,
        name: '',
        parameters: 'parameters',
        pollers: [],
        port: { name: 'Port', value: 5700 },
        type: {}
      },
      inputs: [
        {
          fieldName: 'name',
          group: 'main',
          label: t(labelName),
          required: true,
          type: InputType.Text
        },
        {
          fieldName: 'description',
          group: 'main',
          label: t(labelDescription),
          text: {
            multilineRows: 3
          },
          type: InputType.Text
        },
        {
          additionalLabel: t(labelPollers),
          additionalLabelClassName: classes.additionalLabel,
          fieldName: 'pollers',
          group: 'main',
          label: t(labelPollers),
          required: true,
          type: InputType.SingleConnectedAutocomplete
        },
        {
          additionalLabel: t(labelType),
          additionalLabelClassName: classes.additionalLabel,
          fieldName: 'type',
          group: 'main',
          label: t(labelSelectType),
          required: true,
          type: InputType.SingleConnectedAutocomplete
        },
        {
          additionalLabel: t(labelParameters),
          additionalLabelClassName: classes.additionalLabel,
          custom: {
            Component: Parameters
          },
          fieldName: 'parameters',
          group: 'main',
          label: t(labelParameters),
          type: InputType.Custom
        },
        {
          additionalLabel: t(labelPort),
          additionalLabelClassName: classes.additionalLabel,
          fieldName: 'port',
          grid: {
            columns: [
              {
                fieldName: 'port.name',
                label: t(labelName),
                type: InputType.Text
              },
              {
                fieldName: 'port.value',
                label: t(labelValue),
                type: InputType.Text
              }
            ]
          },
          group: 'main',
          label: t(labelPort),
          type: InputType.Grid
        }
      ],
      submit: (values, bag) => onSubmit?.(values, bag),
      validationSchema: object({
        description: string()
          .label(t(labelDescription) || '')
          .max(
            180,
            (p) =>
              `${p.label} ${t(labelMustBeMost)} ${p.max} ${t(labelCharacters)}`
          )
          .nullable(),
        name: string()
          .label(t(labelName))
          .min(3, ({ min, label }) => t(labelMustBeAtLeast, { label, min }))
          .max(50, ({ max, label }) => t(labelMustBeMost, { label, max }))
          .required(t(labelRequired) as string)
      })
    }),
    [resource, onSubmit]
  );

  const Actions = useCallback(
    () => (
      <FormActions<ConnectorsProperties>
        labels={actionsLabels}
        variant={variant}
        onCancel={onCancel}
      />
    ),
    [onCancel, variant]
  );

  return (
    <div className={classes.form}>
      <Form<ConnectorsProperties> {...formProps} Buttons={Actions} />
    </div>
  );
};

export default ConnectorsForm;
