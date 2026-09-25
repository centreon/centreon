import { Box } from '@mui/material';

import { Form, Group, InputProps } from '@centreon/ui';
import { FormActions, FormActionsProps } from '@centreon/ui/components';

import { FormikHelpers, useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { ObjectSchema } from 'yup';

import { formActionsAtom, isFormDirtyAtom } from '../atoms';
import { CloseModalConfirmation } from '../Dialogs';
import { labelCancel, labelSave } from '../translatedLabels';
import { useFormStyles } from './Form.styles';

export type ResourceFormProps = {
  id?: number;
  onSubmit?: (
    values: Record<string, unknown>,
    helpers: FormikHelpers<Record<string, unknown>>
  ) => void;
  mode?: 'add' | 'edit';
  inputs: Array<InputProps>;
  groups: Array<Group>;
  validationSchema: ObjectSchema<object>;
  initialValues: Record<string, unknown>;
  isLoading: boolean;
  hasWriteAccess: boolean;
  // Surfaces whose own chrome carries save and reset — the panel — take them
  // out of the form and drive them through `formActionsAtom` instead.
  areActionsInHeader?: boolean;
} & Pick<FormActionsProps, 'onCancel'>;

export type ResourceFormLabels = {
  actions: FormActionsProps['labels'];
  entity: Record<string, unknown>;
};

const Actions = ({
  onCancel,
  mode
}: {
  onCancel: FormActionsProps['onCancel'];
  mode?: 'add' | 'edit';
}): JSX.Element => {
  const { t } = useTranslation();

  const setIsDirty = useSetAtom(isFormDirtyAtom);

  const { dirty } = useFormikContext();

  useEffect(() => {
    setIsDirty(dirty);
  }, [dirty]);

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelSave),
      update: t(labelSave)
    }
  };

  const variant = equals(mode, 'add') ? 'create' : 'update';

  return (
    <>
      <FormActions
        labels={actionsLabels}
        onCancel={onCancel}
        variant={variant}
      />
      <CloseModalConfirmation />
    </>
  );
};

// Publishes what an outside surface needs to drive the form, from inside the
// Formik context the surface cannot reach.
const PublishedActions = (): JSX.Element => {
  const setIsDirty = useSetAtom(isFormDirtyAtom);
  const setFormActions = useSetAtom(formActionsAtom);

  const { dirty, isValid, isSubmitting, submitForm, resetForm } =
    useFormikContext();

  useEffect(() => {
    setIsDirty(dirty);
  }, [dirty]);

  useEffect(() => {
    setFormActions({
      canReset: dirty && !isSubmitting,
      canSubmit: dirty && isValid && !isSubmitting,
      isSubmitting,
      reset: () => resetForm(),
      submit: () => {
        submitForm();
      }
    });
  }, [dirty, isValid, isSubmitting]);

  useEffect(() => () => setFormActions(null), []);

  return <CloseModalConfirmation />;
};

const ResourceForm = ({
  mode,
  onSubmit,
  onCancel,
  inputs,
  groups,
  validationSchema,
  initialValues,
  isLoading,
  hasWriteAccess,
  areActionsInHeader = false
}: ResourceFormProps): JSX.Element => {
  const { classes } = useFormStyles();

  const getButtons = (): typeof Box => {
    if (!hasWriteAccess) {
      return Box;
    }

    if (areActionsInHeader) {
      return PublishedActions;
    }

    return () => <Actions mode={mode} onCancel={onCancel} />;
  };

  return (
    <Form
      areGroupsOpen
      Buttons={getButtons()}
      groups={groups}
      groupsClassName={classes.groups}
      initialValues={initialValues}
      inputs={inputs}
      isCollapsible
      isLoading={isLoading}
      submit={(values, bag) => onSubmit?.(values, bag)}
      validationSchema={validationSchema}
    />
  );
};

export default ResourceForm;
