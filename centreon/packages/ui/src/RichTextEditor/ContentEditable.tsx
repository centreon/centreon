import { useCallback, useEffect, useLayoutEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { makeStyles } from 'tss-react/mui';
import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

interface StyleProps {
  editable: boolean;
  minInputHeight: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { minInputHeight, editable }) => ({
    container: {
      '& p': {
        margin: 0
      },
      backgroundColor: theme.palette.background.paper,
      border: '1px solid transparent',
      borderRadius: theme.shape.borderRadius,
      padding: theme.spacing(0.5, 1)
    },
    emptyInput: {
      marginTop: '-22px'
    },
    input: {
      minHeight: editable ? minInputHeight : 'min-content',
      outline: '0px solid transparent'
    },
    inputFocused: {
      border: `1px solid ${theme.palette.primary.main}`
    },
    placeholder: {
      color: theme.palette.grey[500],
      pointerEvents: 'none'
    }
  })
);

interface Props {
  editable: boolean;
  editorState?: string;
  hasInitialTextContent?: boolean;
  initialEditorState?: string;
  inputClassname?: string;
  minInputHeight: number;
  placeholder: string;
  resetEditorToInitialStateCondition?: () => boolean;
}

const ContentEditable = ({
  minInputHeight,
  inputClassname,
  placeholder,
  hasInitialTextContent,
  editable,
  editorState,
  resetEditorToInitialStateCondition,
  initialEditorState
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ editable, minInputHeight });
  const { t } = useTranslation();

  const [editor] = useLexicalComposerContext();
  const [isEditable, setEditable] = useState(false);
  const [isFocused, setFocused] = useState(false);
  const [root, setRoot] = useState('');

  const ref = useCallback(
    (rootElement: null | HTMLElement) => {
      editor.setRootElement(rootElement);
    },
    [editor]
  );

  useLayoutEffect(() => {
    setEditable(editor.isEditable());

    if (editorState && !editable) {
      const newEditorState = editor.parseEditorState(editorState);

      editor.setEditorState(newEditorState);
    }

    return editor.registerEditableListener((currentIsEditable) => {
      setEditable(currentIsEditable);
    });
  }, [editor, editorState]);

  useEffect(() => {
    editor.registerTextContentListener((currentRoot) => {
      setRoot(currentRoot);
    });

    if (!hasInitialTextContent) {
      return;
    }

    setRoot(' ');
  }, [editor]);

  useEffect(() => {
    const shouldResetEditorToInitialState =
      resetEditorToInitialStateCondition?.();

    if (!shouldResetEditorToInitialState || isNil(initialEditorState)) {
      return;
    }

    const newEditorState = editor.parseEditorState(initialEditorState);

    editor.setEditorState(newEditorState);
  }, [editorState]);

  const isTextEmpty = isEmpty(root);

  return (
    <div className={cx(classes.container, isFocused && classes.inputFocused)}>
      {editable && isTextEmpty && (
        <Typography className={classes.placeholder}>
          {t(placeholder)}
        </Typography>
      )}
      <div
        className={cx(
          isTextEmpty && classes.emptyInput,
          classes.input,
          inputClassname
        )}
        contentEditable={isEditable}
        ref={ref}
        onBlur={(): void => setFocused(false)}
        onFocus={(): void => setFocused(true)}
      />
    </div>
  );
};

export default ContentEditable;
