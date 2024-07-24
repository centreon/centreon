import { ReactElement, useCallback, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { find, propEq } from 'ramda';

import { Form, FormProps, InputType } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import {
  labelCancel,
  labelCreate,
  labelDescription,
  labelName,
  labelParameters,
  labelPollers,
  labelPort,
  labelSelectPollers,
  labelSelectType,
  labelType,
  labelUpdate
} from '../../translatedLabels';
import { getPollersForConnectorTypeEndpoint } from '../../api/endpoints';
import { availableConnectorTypes, defaultParameters } from '../utils';
import { useFormStyles } from '../useModalStyles';
import Parameters from '../Parameters/Parameters';
import Port from '../Parameters/Port';
import ConnectorType from '../ConnectorType/ConnectorType';

import useValidationSchema from './useValidationSchema';

import {
  FormActions,
  FormActionsProps
} from 'packages/ui/src/components/Form/FormActions';

export type ConnectorsProperties = {
  description?: string | null;
  name: string;
  parameters;
  pollers;
  type: number;
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

  const { validationSchema } = useValidationSchema();

  const formProps = useMemo<FormProps<ConnectorsProperties>>(
    () => ({
      initialValues: resource ?? {
        description: null,
        name: '',
        parameters: { port: 5700, vcenters: [defaultParameters] },
        pollers: [],
        type:
          find(propEq('vmware_v6', 'name'), availableConnectorTypes)?.id || 1
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
          custom: {
            Component: ConnectorType
          },
          fieldName: 'type',
          group: 'main',
          label: t(labelSelectType),
          required: true,
          type: InputType.Custom
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
      validationSchema
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
