import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Form } from '@centreon/ui';
import { FormActions, FormActionsProps } from '@centreon/ui/components';

import { CloseModalConfirmation } from '../../Dialogs';
import useGetDetails from '../../api/useGetOne';

import { isFormDirtyAtom } from '../../atoms';

import { labelCancel, labelCreate, labelUpdate } from '../../translatedLabels';

import { useFormStyles } from './Form.styles';

export type HostGroupFormProps = {
  id?: number;
  onSubmit?;
  mode?: 'add' | 'edit';
} & Pick<FormActionsProps, 'onCancel'>;

export type ConnectorFormLabels = {
  actions: FormActionsProps['labels'];
  entity;
};

const HostGroupForm = ({
  id,
  mode,
  onSubmit,
  onCancel,
  inputs,
  groups,
  validationSchema,
  defaultValues
}: HostGroupFormProps): JSX.Element => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelCreate),
      update: t(labelUpdate)
    }
  };

  // to check later for quit the modal if the id does not exists
  const { data, isLoading } = useGetDetails({
    id
  });

  // const initialValues = data && equals(mode, 'edit') ? data : defaultValues;
  const initialValues =
    data && equals(mode, 'edit')
      ? {
          ...data,
          hosts: [{ id: 1, name: 'Host_1' }],
          resourceAccessRules: [
            { id: 1, name: 'Rule_1' },
            { id: 2, name: 'Rule_2' }
          ]
        } // for now
      : defaultValues;

  const Actions = (): JSX.Element => {
    const setIsDirty = useSetAtom(isFormDirtyAtom);

    const { dirty } = useFormikContext();

    setIsDirty(dirty);

    return (
      <>
        <FormActions
          labels={actionsLabels}
          variant={equals(mode, 'add') ? 'create' : 'update'}
          onCancel={onCancel}
        />
        <CloseModalConfirmation />
      </>
    );
  };

  return (
    <div className={classes.form}>
      <Form
        Buttons={Actions}
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
    </div>
  );
};

export default HostGroupForm;
