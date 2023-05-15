import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { split, path } from 'ramda';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { labelTypeYourTextHere } from '../../translatedLabels';

const EmailBody = (): JSX.Element => {
  const { t } = useTranslation();
  const { setFieldValue, values, initialValues, errors } =
    useFormikContext<FormikValues>();

  const getEditorState = (state: unknown): void => {
    setFieldValue('messages.message', JSON.stringify(state));
  };

  const value = values?.messages.message;

  const fieldNamePath = split('.', 'messages.message');

  const error = (path(fieldNamePath, errors) as string) || undefined;

  return useMemoComponent({
    Component: (
      <RichTextEditor
        editable
        editorState={value}
        error={error}
        getEditorState={getEditorState}
        initialEditorState={initialValues?.messages.message}
        minInputHeight={120}
        namespace="Email body"
        placeholder={t(labelTypeYourTextHere)}
        toolbarPositions="end"
      />
    ),
    memoProps: [value, error]
  });
};

export default EmailBody;
