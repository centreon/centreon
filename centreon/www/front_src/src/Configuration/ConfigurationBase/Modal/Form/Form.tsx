import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { Form, Group, InputProps } from '@centreon/ui';
import { FormActions, FormActionsProps } from '@centreon/ui/components';
import { CloseModalConfirmation } from '../../Dialogs';

import { isFormDirtyAtom } from '../../atoms';
import { labelCancel, labelCreate, labelUpdate } from '../../translatedLabels';
import { useFormStyles } from './Form.styles';

export type HostGroupFormProps = {
  id?: number;
  onSubmit?;
  mode?: 'add' | 'edit';
  inputs: InputProps;
  groups: Array<Group>;
  validationSchema;
  initialValues;
  isLoading: boolean;
} & Pick<FormActionsProps, 'onCancel'>;

export type ConnectorFormLabels = {
  actions: FormActionsProps['labels'];
  entity;
};

const Actions =
  ({ onCancel, mode }) =>
  (): JSX.Element => {
    const { t } = useTranslation();

    const setIsDirty = useSetAtom(isFormDirtyAtom);

    const { dirty } = useFormikContext();

    useEffect(() => {
      setIsDirty(dirty);
    }, [dirty]);

    const actionsLabels = {
      cancel: t(labelCancel),
      submit: {
        create: t(labelCreate),
        update: t(labelUpdate)
      }
    };

    const variant = equals(mode, 'add') ? 'create' : 'update';

    return (
      <>
        <FormActions
          labels={actionsLabels}
          variant={variant}
          onCancel={onCancel}
        />
        <CloseModalConfirmation />
      </>
    );
  };

const HostGroupForm = ({
  mode,
  onSubmit,
  onCancel,
  inputs,
  groups,
  validationSchema,
  initialValues,
  isLoading
}: HostGroupFormProps): JSX.Element => {
  const { classes } = useFormStyles();

  return (
    <Form
      Buttons={Actions({ onCancel, mode })}
      isCollapsible
      areGroupsOpen
      initialValues={initialValues}
      inputs={inputs}
      groups={groups}
      isLoading={isLoading}
      submit={(values, bag) => onSubmit?.(values, bag)}
      validationSchema={validationSchema}
      groupsClassName={classes.groups}
    />
  );
};

export default HostGroupForm;
