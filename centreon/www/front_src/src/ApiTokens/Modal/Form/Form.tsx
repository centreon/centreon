import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Form } from '@centreon/ui';
import { FormActions } from '@centreon/ui/components';

import useInitilialValues from './useInitilialValues';
import useValidationSchema from './useValidationSchema';

import { tokenAtom } from '../../atoms';
import { labelCancel, labelGenerateToken } from '../../translatedLabels';
import useForm from './useForm';
import useFormInputs from './useFormInputs';

const Actions =
  ({ close }) =>
  (): JSX.Element => {
    const { t } = useTranslation();

    const token = useAtomValue(tokenAtom);

    const actionsLabels = {
      cancel: t(labelCancel),
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
