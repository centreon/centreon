import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Form } from '@centreon/ui';
import { FormActions } from '@centreon/ui/components';

import useForm from './useForm';
import useFormInputs from './useFormInputs';
import useInitilialValues from './useInitilialValues';
import useValidationSchema from './useValidationSchema';

import { tokenAtom } from '../../atoms';
import {
  labelCancel,
  labelDone,
  labelGenerateToken
} from '../../translatedLabels';

const Actions =
  ({ close }) =>
  (): JSX.Element => {
    const { t } = useTranslation();
    const token = useAtomValue(tokenAtom);

    const actionsLabels = {
      cancel: t(token ? labelDone : labelCancel),
      submit: {
        create: t(labelGenerateToken)
      }
    };

    return (
      <FormActions
        labels={actionsLabels}
        variant={'create'}
        onCancel={close}
        isSubmitButtonVisible={!token}
      />
    );
  };

const TokenForm = ({ close }): JSX.Element => {
  const { initialValues } = useInitilialValues();
  const { validationSchema } = useValidationSchema();
  const { inputs } = useFormInputs();

  const { createToken } = useForm();

  return (
    <Form
      Buttons={Actions({ close })}
      initialValues={initialValues}
      inputs={inputs}
      submit={(values, bag) => createToken?.(values, bag)}
      validationSchema={validationSchema}
    />
  );
};

export default TokenForm;
