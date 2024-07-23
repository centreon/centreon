import { ReactElement, useCallback, useMemo } from 'react';

import { string, object, number, array } from 'yup';
import { useTranslation } from 'react-i18next';

import { Form, FormProps, InputType } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import {
  labelAteastOnePollerIsRequired,
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
  labelSelectPollers,
  labelSelectType,
  labelType,
  labelUpdate
} from '../translatedLabels';
import { getPollersForConnectorTypeEndpoint } from '../api/endpoints';

import { defaultParameters } from './utils';
import { useFormStyles } from './useModalStyles';
import Parameters from './Parameters/Parameters';
import Port from './Parameters/Port';

import {
  FormActions,
  FormActionsProps
} from 'packages/ui/src/components/Form/FormActions';

export type ConnectorsProperties = {
  description?: string | null;
  name: string;
  parameters;
  pollers;
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

  const selectEntryValidationSchema = object().shape({
    id: number().required('Required'),
    name: string().required('Required')
  });

  const formProps = useMemo<FormProps<ConnectorsProperties>>(
    () => ({
      initialValues: resource ?? {
        description: null,
        name: '',
        parameters: { port: 5700, vcenters: [defaultParameters] },
        pollers: [],
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
          additionalLabel: t(labelType),
          additionalLabelClassName: classes.additionalLabel,
          fieldName: 'type',
          group: 'main',
          label: t(labelSelectType),
          required: true,
          type: InputType.SingleAutocomplete
        },
        {
          additionalLabel: t(labelPollers),
          additionalLabelClassName: classes.additionalLabel,
          connectedAutocomplete: {
            additionalConditionParameters: [],
            endpoint: getPollersForConnectorTypeEndpoint({})
          },
          fieldName: 'pollers',
          group: 'main',
          label: t(labelSelectPollers),
          required: true,
          type: InputType.MultiConnectedAutocomplete
        },
        {
          additionalLabel: t(labelParameters),
          additionalLabelClassName: classes.additionalLabel,
          custom: {
            Component: Parameters
          },
          fieldName: 'parameters.vcenters',
          group: 'main',
          label: t(labelParameters),
          type: InputType.Custom
        },
        {
          additionalLabel: t(labelPort),
          additionalLabelClassName: classes.additionalLabel,
          custom: {
            Component: Port
          },
          fieldName: 'prameters.port',
          group: 'main',
          label: t(labelPort),
          type: InputType.Custom
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
          .required(t(labelRequired) as string),
        pollers: array()
          .of(selectEntryValidationSchema)
          .min(1, t(labelAteastOnePollerIsRequired))
        // port: object().shape({
        //   id: number().required('Required'),
        //   name: string().required('Required')
        // })
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
