/* eslint-disable react/no-unstable-nested-components */
import { ReactElement } from 'react';

import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Form, FormProps } from '@centreon/ui';
import { FormVariant } from '@centreon/ui/components';

import { isFormDirtyAtom } from '../../atoms';
import { labelCancel, labelCreate, labelUpdate } from '../../translatedLabels';
import { AdditionalConnectorConfiguration } from '../models';
import { useFormStyles } from '../useModalStyles';

import useFormInitialValues from './useFormInitialValues';
import useFormInputs from './useFormInputs';
import useValidationSchema from './useValidationSchema';

import { FormActions, FormActionsProps } from '@centreon/ui/components';

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
  const { validationSchema } = useValidationSchema({ variant });
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

  const Actions = (): JSX.Element => {
    const setIsDirty = useSetAtom(isFormDirtyAtom);

    const { dirty } = useFormikContext();

    setIsDirty(dirty);

    return (
      <FormActions<AdditionalConnectorConfiguration>
        labels={actionsLabels}
        variant={variant}
        onCancel={onCancel}
      />
    );
  };

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
