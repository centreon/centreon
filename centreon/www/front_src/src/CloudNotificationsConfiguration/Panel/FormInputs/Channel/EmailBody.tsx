// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import { $generateHtmlFromNodes } from '@lexical/html';
import { FormikValues, useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import type { LexicalEditor } from 'lexical';
import { path } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelTypeYourTextHere } from '../../../translatedLabels';
import { htmlEmailBodyAtom } from '../../atom';
import { useStyles } from '../Inputs.styles';

const EmailBody = (): ReactElement => {
  const { classes } = useStyles({});
  const { t } = useTranslation();

  const sethtmlEmailBody = useSetAtom(htmlEmailBodyAtom);

  const { setFieldValue, values, initialValues, errors, touched, handleBlur } =
    useFormikContext<FormikValues>();

  const getEditorState = (state: unknown): void => {
    setFieldValue('messages.message', JSON.stringify(state));
  };

  const initialize = (editor: LexicalEditor): void => {
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
        contentClassName={classes.textEditor}
        displayMacrosButton
        editable
        editorState={value}
        error={(error as string) || undefined}
        getEditorState={getEditorState}
        initialEditorState={initialValues?.messages.message}
        initialize={initialize}
        minInputHeight={120}
        namespace="EmailBody"
        onBlur={handleBlur('messages.message')}
        placeholder={t(labelTypeYourTextHere) as string}
        setHtmlString={sethtmlEmailBody}
        toolbarClassName={classes.editorToolbar}
        toolbarPositions="end"
      />
    ),
    memoProps: [value, error]
  });
};

export default EmailBody;
