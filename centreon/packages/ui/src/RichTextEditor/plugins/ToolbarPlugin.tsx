import { useState, useCallback, useEffect, useMemo } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $getSelection,
  $isRangeSelection,
  EditorState,
  FORMAT_TEXT_COMMAND,
  SELECTION_CHANGE_COMMAND,
  TextFormatType
} from 'lexical';
import { mergeRegister } from '@lexical/utils';

import FormatBoldIcon from '@mui/icons-material/FormatBold';
import FormatItalicIcon from '@mui/icons-material/FormatItalic';
import FormatUnderlinedIcon from '@mui/icons-material/FormatUnderlined';
import StrikethroughSIcon from '@mui/icons-material/StrikethroughS';
import { Box, Button, ButtonGroup } from '@mui/material';

const LowPriority = 1;

const useStyles = makeStyles()((theme) => ({
  button: {
    '&:hover': {
      color: theme.palette.primary.main
    }
  },
  buttonSelected: {
    backgroundColor: theme.palette.primary.main,
    color: theme.palette.primary.contrastText
  },
  container: {
    marginBottom: theme.spacing(1)
  }
}));

interface Props {
  getEditorState?: (editorState: EditorState) => void;
}

const ToolbarPlugin = ({ getEditorState }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [isStrikeThrough, setIsStrikeThrough] = useState(false);

  const [editor] = useLexicalComposerContext();

  const updateToolbar = useCallback((): void => {
    const selection = $getSelection();

    if (!$isRangeSelection(selection)) {
      return;
    }

    setIsBold(selection.hasFormat('bold'));
    setIsItalic(selection.hasFormat('italic'));
    setIsUnderline(selection.hasFormat('underline'));
    setIsStrikeThrough(selection.hasFormat('strikethrough'));
  }, [editor]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => {
        editorState.read(updateToolbar);
        getEditorState?.(editorState);
      }),
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          updateToolbar();

          return false;
        },
        LowPriority
      )
    );
  }, [editor, updateToolbar]);

  const toggleTextFormat = (textFormat: TextFormatType) => (): void => {
    editor.dispatchCommand(FORMAT_TEXT_COMMAND, textFormat);
  };

  const formatButtons = useMemo(
    () => [
      {
        Icon: FormatBoldIcon,
        format: 'bold',
        isSelected: isBold
      },
      {
        Icon: FormatItalicIcon,
        format: 'italic',
        isSelected: isItalic
      },
      {
        Icon: FormatUnderlinedIcon,
        format: 'underline',
        isSelected: isUnderline
      },
      {
        Icon: StrikethroughSIcon,
        format: 'strikethrough',
        isSelected: isStrikeThrough
      }
    ],
    [isBold, isItalic, isUnderline, isStrikeThrough]
  );

  return (
    <Box className={classes.container}>
      <ButtonGroup size="small">
        {formatButtons.map(({ Icon, format, isSelected }) => (
          <Button
            className={cx(isSelected && classes.buttonSelected, classes.button)}
            key={format}
            onClick={toggleTextFormat(format)}
          >
            <Icon fontSize="small" />
          </Button>
        ))}
      </ButtonGroup>
    </Box>
  );
};

export default ToolbarPlugin;
