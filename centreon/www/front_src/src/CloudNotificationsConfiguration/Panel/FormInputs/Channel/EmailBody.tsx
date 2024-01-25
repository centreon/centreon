import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { path } from 'ramda';
import { useSetAtom } from 'jotai';
import { $generateHtmlFromNodes } from '@lexical/html';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { labelTypeYourTextHere } from '../../../translatedLabels';
import { useStyles } from '../Inputs.styles';
import { htmlEmailBodyAtom } from '../../atom';

const EmailBody = (): JSX.Element => {
  const { classes } = useStyles({});
  const { t } = useTranslation();

  const sethtmlEmailBody = useSetAtom(htmlEmailBodyAtom);

  const { setFieldValue, values, initialValues, errors, touched, handleBlur } =
    useFormikContext<FormikValues>();

  const getEditorState = (state: unknown): void => {
    setFieldValue('messages.message', JSON.stringify(state));
  };

  const initialize = (editor): void => {
    editor.update(() => {
      const htmlString = $generateHtmlFromNodes(editor, null);
      sethtmlEmailBody(htmlString);
    });
  };

  const value = path(['messages', 'message'], values);

  const error = path(['messages', 'message'], touched)
    ? path(['messages', 'message'], errors)
    : undefined;

  return useMemoComponent({
    Component: (
      <RichTextEditor
        displayMacrosButton
        editable
        contentClassName={classes.textEditor}
        editorState={value}
        error={(error as string) || undefined}
        getEditorState={getEditorState}
        initialEditorState={initialValues?.messages.message}
        initialize={initialize}
        minInputHeight={120}
        namespace="EmailBody"
        placeholder={t(labelTypeYourTextHere) as string}
        setHtmlString={sethtmlEmailBody}
        toolbarClassName={classes.editorToolbar}
        toolbarPositions="end"
        onBlur={handleBlur('messages.message')}
      />
    ),
    memoProps: [value, error]
  });
};

export default EmailBody;
