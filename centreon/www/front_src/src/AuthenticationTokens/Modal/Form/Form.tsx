import { useTranslation } from 'react-i18next';

import { Form } from '@centreon/ui';
import { FormActions } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { tokenAtom } from '../../atoms';
import {
  labelCancel,
  labelDone,
  labelGenerateToken
} from '../../translatedLabels';
import useForm from './useForm';
import useFormInputs from './useFormInputs';
import useInitilialValues from './useInitilialValues';
import useValidationSchema from './useValidationSchema';

const Actions =
  ({ close, token }) =>
  (): JSX.Element => {
    const { t } = useTranslation();
    const { values } = useFormikContext();

    const actionsLabels = {
      cancel: t(labelCancel),
      submit: {
        create: t(token ? labelDone : labelGenerateToken)
      }
    };

    const disableSubmit =
      equals(values?.duration.id, 'customize') && isNil(values?.customizeDate);

    return (
      <FormActions
        labels={actionsLabels}
        variant={'create'}
        onCancel={close}
        isCancelButtonVisible={!token}
        disableSubmit={disableSubmit}
      />
    );
  };

const TokenForm = ({ close }): JSX.Element => {
  const { initialValues } = useInitilialValues();
  const { validationSchema } = useValidationSchema();
  const { inputs } = useFormInputs();

  const { createToken } = useForm();

  const token = useAtomValue(tokenAtom);

  return (
    <Form
      Buttons={Actions({ close, token })}
      initialValues={initialValues}
      inputs={inputs}
      submit={(values, bag) => (token ? close() : createToken?.(values, bag))}
      validationSchema={validationSchema}
    />
  );
};

export default TokenForm;
