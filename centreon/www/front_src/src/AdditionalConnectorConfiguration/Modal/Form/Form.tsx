import { ReactElement, useCallback } from 'react';

import { useTranslation } from 'react-i18next';

import { Form, FormProps } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import { labelCancel, labelCreate, labelUpdate } from '../../translatedLabels';
import { useFormStyles } from '../useModalStyles';
import { AdditionalConnectorConfiguration } from '../models';

import useValidationSchema from './useValidationSchema';
import useFormInitialValues from './useFormInitialValues';
import useFormInputs from './useFormInputs';

import {
  FormActions,
  FormActionsProps
} from 'packages/ui/src/components/Form/FormActions';

export type AdditionalConnectorFormProps = {
  connectorId?: number;
  onSubmit?: FormProps<AdditionalConnectorConfiguration>['submit'];
  variant?: FormVariant;
} & Pick<FormActionsProps, 'onCancel'>;

export type ConnectorFormLabels = {
  actions: FormActionsProps['labels'];
  entity;
};

const AdditionalConnector = ({
  variant = 'create',
  onSubmit,
  onCancel,
  connectorId
}: AdditionalConnectorFormProps): ReactElement => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelCreate),
      update: t(labelUpdate)
    }
  };

  const { inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();
  const { initialValues, isLoading } = useFormInitialValues({
    id: connectorId,
    variant
  });

  const formProps: FormProps<AdditionalConnectorConfiguration> = {
    initialValues,
    inputs,
    isLoading,
    submit: (values, bag) => onSubmit?.(values, bag),
    validationSchema
  };

  const Actions = useCallback(
    () => (
      <FormActions<AdditionalConnectorConfiguration>
        labels={actionsLabels}
        variant={variant}
        onCancel={onCancel}
      />
    ),
    [onCancel, variant]
  );

  return (
    <div className={classes.form}>
      <Form<AdditionalConnectorConfiguration>
        {...formProps}
        Buttons={Actions}
      />
    </div>
  );
};

export default AdditionalConnector;
