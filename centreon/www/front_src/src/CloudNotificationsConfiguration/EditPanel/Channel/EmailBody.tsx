import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { equals, path } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { ThemeMode } from '@centreon/ui-context';
import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { labelTypeYourTextHere } from '../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  textEditor: {
    backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
      ? theme.palette.background.default
      : theme.palette.common.white
  }
}));

const EmailBody = (): JSX.Element => {
  const { classes } = useStyle();

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
        contentClassName={classes.textEditor}
        editorState={value}
        error={(error as string) || undefined}
        getEditorState={getEditorState}
        initialEditorState={initialValues?.messages.message}
        minInputHeight={120}
        namespace="EmailBody"
        placeholder={t(labelTypeYourTextHere) as string}
        toolbarPositions="end"
        onBlur={handleBlur('messages.message')}
      />
    ),
    memoProps: [value, error]
  });
};

export default EmailBody;
