import { useState, useCallback, useEffect, useMemo } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $getSelection,
  $isRangeSelection,
  EditorState,
  FORMAT_TEXT_COMMAND,
  RangeSelection,
  SELECTION_CHANGE_COMMAND,
  TextFormatType,
  ElementNode,
  TextNode
} from 'lexical';
import { $isLinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link';
import { $isAtNodeEnd } from '@lexical/selection';
import { mergeRegister } from '@lexical/utils';
import { useAtom } from 'jotai';

import FormatBoldIcon from '@mui/icons-material/FormatBold';
import FormatItalicIcon from '@mui/icons-material/FormatItalic';
import FormatUnderlinedIcon from '@mui/icons-material/FormatUnderlined';
import StrikethroughSIcon from '@mui/icons-material/StrikethroughS';
import LinkIcon from '@mui/icons-material/Link';
import { alpha } from '@mui/material';

import { IconButton } from '../../..';
import { isInsertingLinkAtom } from '../../atoms';

const LowPriority = 1;

const useStyles = makeStyles()((theme) => ({
  buttonSelected: {
    backgroundColor: alpha(
      theme.palette.primary.main,
      theme.palette.action.activatedOpacity
    )
  },
  container: {
    columnGap: theme.spacing(1),
    display: 'flex',
    marginBottom: theme.spacing(1)
  }
}));

const getSelectedNode = (selection: RangeSelection): ElementNode | TextNode => {
  const { anchor } = selection;
  const { focus } = selection;
  const anchorNode = selection.anchor.getNode();
  const focusNode = selection.focus.getNode();
  if (anchorNode === focusNode) {
    return anchorNode;
  }
  const isBackward = selection.isBackward();
  if (isBackward) {
    return $isAtNodeEnd(focus) ? anchorNode : focusNode;
  }

  return $isAtNodeEnd(anchor) ? focusNode : anchorNode;
};

interface Props {
  getEditorState?: (editorState: EditorState) => void;
}

const FormatButtons = ({ getEditorState }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [isStrikeThrough, setIsStrikeThrough] = useState(false);
  const [isLink, setIsLink] = useAtom(isInsertingLinkAtom);

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

    const node = getSelectedNode(selection);
    const parent = node.getParent();
    if ($isLinkNode(parent) || $isLinkNode(node)) {
      setIsLink(true);
    } else {
      setIsLink(false);
    }
  }, [editor]);

  const insertLink = useCallback(() => {
    if (!isLink) {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, 'https://');
    } else {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    }
  }, [editor, isLink]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => {
        editorState.read(() => {
          updateToolbar();
        });

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
        isSelected: isBold,
        onClickFunction: toggleTextFormat('bold'),
        type: 'bold'
      },
      {
        Icon: FormatItalicIcon,
        isSelected: isItalic,
        onClickFunction: toggleTextFormat('italic'),
        type: 'italic'
      },
      {
        Icon: FormatUnderlinedIcon,
        isSelected: isUnderline,
        onClickFunction: toggleTextFormat('underline'),
        type: 'underline'
      },
      {
        Icon: StrikethroughSIcon,
        isSelected: isStrikeThrough,
        onClickFunction: toggleTextFormat('strikethrough'),
        type: 'strikethrough'
      },
      {
        Icon: LinkIcon,
        isSelected: isLink,
        onClickFunction: insertLink,
        type: 'link'
      }
    ],
    [isBold, isItalic, isUnderline, isStrikeThrough, isLink]
  );

  return (
    <>
      {formatButtons.map(({ Icon, onClickFunction, isSelected, type }) => (
        <IconButton
          ariaLabel={type}
          className={cx(isSelected && classes.buttonSelected)}
          key={type}
          size="medium"
          title={type}
          tooltipPlacement="top"
          onClick={onClickFunction}
        >
          <Icon />
        </IconButton>
      ))}
    </>
  );
};

export default FormatButtons;
