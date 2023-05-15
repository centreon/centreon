import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { path } from 'ramda';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { labelTypeYourTextHere } from '../../translatedLabels';

const EmailBody = (): JSX.Element => {
  const { t } = useTranslation();
  const { setFieldValue, values, initialValues, errors, touched, handleBlur } =
    useFormikContext<FormikValues>();

  const getEditorState = (state: unknown): void => {
    setFieldValue('messages.message', JSON.stringify(state));
  };

  const value = path(['messages', 'message'], values);

  const error = path(['messages', 'message'], touched)
    ? path(['messages', 'message'], errors)
    : undefined;

  return useMemoComponent({
    Component: (
      <RichTextEditor
        editable
        editorState={value}
        error={(error as string) || undefined}
        getEditorState={getEditorState}
        initialEditorState={initialValues?.messages.message}
        minInputHeight={120}
        namespace="Email body"
        placeholder={t(labelTypeYourTextHere)}
        toolbarPositions="end"
        onBlur={handleBlur('messages.message')}
      />
    ),
    memoProps: [value, error]
  });
};

export default EmailBody;
