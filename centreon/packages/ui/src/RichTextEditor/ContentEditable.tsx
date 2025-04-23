import { useCallback, useEffect, useLayoutEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

interface StyleProps {
  editable: boolean;
  error?: string;
  minInputHeight: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { minInputHeight, editable, error }) => ({
    container: {
      backgroundColor: theme.palette.background.paper,
      border: error
        ? `1px solid ${theme.palette.error.main}`
        : '1px solid transparent',
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
      border: error
        ? `1px solid ${theme.palette.error.main}`
        : `1px solid ${theme.palette.primary.main}`
    },
    notEditable: {
      backgroundColor: 'transparent'
    },
    placeholder: {
      color: theme.palette.grey[500],
      pointerEvents: 'none'
    },
    root: {
      '& p,h1,h2,h3,h4,h5,h6,span': {
        margin: 0
      }
    }
  })
);

interface Props {
  className?: string;
  disabled?: boolean;
  editable: boolean;
  editorState?: string;
  error?: string;
  hasInitialTextContent?: boolean;
  initialEditorState?: string;
  initialize?: (editor) => void;
  inputClassname?: string;
  minInputHeight: number;
  namespace: string;
  onBlur?: (e: string) => void;
  placeholder: string;
  resetEditorToInitialStateCondition?: () => boolean;
}

const defaultState =
  '{"root":{"children":[{"children":[],"direction":null,"format":"","indent":0,"type":"paragraph","version":1}],"direction":null,"format":"","indent":0,"type":"root","version":1}}';

const ContentEditable = ({
  minInputHeight,
  inputClassname,
  placeholder,
  hasInitialTextContent,
  editable,
  editorState,
  namespace,
  resetEditorToInitialStateCondition,
  initialEditorState,
  error,
  onBlur,
  className,
  disabled,
  initialize
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ editable, error, minInputHeight });
  const { t } = useTranslation();

  const [editor] = useLexicalComposerContext();
  const [isFocused, setFocused] = useState(false);
  const [root, setRoot] = useState('');

  const ref = useCallback(
    (rootElement: null | HTMLElement) => {
      editor.setRootElement(rootElement);
    },
    [editor]
  );

  useLayoutEffect(() => {
    if (!editable) {
      const newEditorState = editor.parseEditorState(
        editorState || defaultState
      );

      editor.setEditorState(newEditorState);
    }
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

    if (!shouldResetEditorToInitialState) {
      return;
    }

    const newEditorState = editor.parseEditorState(
      initialEditorState || defaultState
    );

    editor.setEditorState(newEditorState);
  }, [editorState]);

  const isTextEmpty =
    isEmpty(root) &&
    !editor.getEditorState().toJSON().root.children?.[0]?.children?.length;

  const handleBlur = (event: React.FocusEvent<HTMLInputElement>): void => {
    setFocused(false);
    onBlur?.(event);
  };

  const isEditable = editor.isEditable();

  useEffect(() => {
    if (isNil(disabled)) {
      return;
    }

    editor.setEditable(!disabled);
  }, [disabled]);

  useEffect(() => {
    initialize?.(editor);
  }, []);

  return (
    <div
      className={cx(
        classes.root,
        editable && classes.container,
        !isEditable && !disabled && classes.notEditable,
        className,
        !disabled && isFocused && classes.inputFocused
      )}
    >
      {editable && isTextEmpty && (
        <Typography className={classes.placeholder}>
          {t(placeholder)}
        </Typography>
      )}
      <div
        aria-label={namespace}
        className={cx(
          editable && isTextEmpty && classes.emptyInput,
          classes.input,
          inputClassname
        )}
        contentEditable={isEditable}
        data-testid={namespace}
        ref={ref}
        onBlur={handleBlur}
        onFocus={(): void => setFocused(true)}
      />
    </div>
  );
};

export default ContentEditable;
