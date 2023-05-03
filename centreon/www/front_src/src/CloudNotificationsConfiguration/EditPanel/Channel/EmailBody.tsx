import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { labelTypeYourTextHere } from '../../translatedLabels';

const EmailBody = (): JSX.Element => {
  const { t } = useTranslation();
  const { setFieldValue, values, initialValues } =
    useFormikContext<FormikValues>();

  const getEditorState = (state: unknown): void => {
    setFieldValue('messages.message', JSON.stringify(state));
  };

  const value = values?.messages.message;

  return useMemoComponent({
    Component: (
      <div>
        <RichTextEditor
          editable
          editorState={value}
          getEditorState={getEditorState}
          initialEditorState={initialValues?.messages.message}
          minInputHeight={120}
          namespace="Email body"
          placeholder={t(labelTypeYourTextHere)}
          toolbarPositions="end"
        />
      </div>
    ),
    memoProps: [value]
  });
};

export default EmailBody;
