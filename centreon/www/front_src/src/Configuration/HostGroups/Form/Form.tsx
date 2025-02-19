import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Form, FormProps } from '@centreon/ui';

import { isFormDirtyAtom } from '../atoms';
import { HostGroupItem } from '../models';
import { labelCancel, labelCreate, labelUpdate } from '../translatedLabels';

import useFormInitialValues from './useFormInitialValues';
import useFormInputs from './useFormInputs';
import useValidationSchema from './useValidationSchema';

import { FormActions, FormActionsProps } from '@centreon/ui/components';
import { equals } from 'ramda';
import { useSearchParams } from 'react-router';
import CloseModalConfirmation from './CloseModalConfirmation';
import { useFormStyles } from './Form.styles';

export type HostGroupFormProps = {
  id?: number;
  onSubmit?: FormProps<HostGroupItem>['submit'];
  mode?: 'add' | 'edit';
} & Pick<FormActionsProps, 'onCancel'>;

export type ConnectorFormLabels = {
  actions: FormActionsProps['labels'];
  entity;
};

const HostGroupForm = ({
  mode = 'add',
  onSubmit,
  onCancel
}: HostGroupFormProps): JSX.Element => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const [searchParams] = useSearchParams(window.location.search);
  const id = searchParams.get('id');

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelCreate),
      update: t(labelUpdate)
    }
  };

  const { inputs, groups } = useFormInputs();
  const { validationSchema } = useValidationSchema();
  const { initialValues, isLoading } = useFormInitialValues({
    id,
    mode
  });

  const Actions = (): JSX.Element => {
    const setIsDirty = useSetAtom(isFormDirtyAtom);

    const { dirty } = useFormikContext();

    setIsDirty(dirty);

    return (
      <>
        <FormActions<HostGroupItem>
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
      <Form<HostGroupItem>
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
