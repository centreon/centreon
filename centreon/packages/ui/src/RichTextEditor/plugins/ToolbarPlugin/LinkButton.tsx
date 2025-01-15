import { useCallback, useEffect } from 'react';

import { $isLinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { $isAtNodeEnd } from '@lexical/selection';
import { mergeRegister } from '@lexical/utils';
import { useAtom } from 'jotai';
import {
  $getSelection,
  $isRangeSelection,
  ElementNode,
  RangeSelection,
  SELECTION_CHANGE_COMMAND,
  TextNode
} from 'lexical';

import LinkIcon from '@mui/icons-material/Link';

import { IconButton } from '../../..';
import { isInsertingLinkAtom } from '../../atoms';

import { useStyles } from './ToolbarPlugin.styles';

const LowPriority = 1;

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
  disabled: boolean;
}

const LinkButton = ({ disabled }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const [isLink, setIsLink] = useAtom(isInsertingLinkAtom);

  const [editor] = useLexicalComposerContext();

  const updateToolbar = useCallback((): void => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) {
      return;
    }

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

  return (
    <IconButton
      ariaLabel="link"
      className={cx(classes.button, { [classes.buttonSelected]: isLink })}
      disabled={disabled}
      key="link"
      size="medium"
      title="link"
      tooltipPlacement="top"
      onClick={insertLink}
    >
      <LinkIcon />
    </IconButton>
  );
};

export default LinkButton;
