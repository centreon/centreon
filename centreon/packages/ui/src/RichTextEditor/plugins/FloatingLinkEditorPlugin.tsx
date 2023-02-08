import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  LexicalEditor
} from 'lexical';
import { mergeRegister } from '@lexical/utils';
import { useAtomValue } from 'jotai';

import { Paper, Popper, Typography } from '@mui/material';

import { getSelectedNode } from '../utils/getSelectedNode';
import { getDOMRangeRect } from '../utils/getDOMRangeRect';
import { isInsertingLinkAtom } from '../atoms';

interface FloatingActionsToolbarPluginProps {
  anchorElem: HTMLElement;
}

interface ToolbarProps extends FloatingActionsToolbarPluginProps {
  editor: LexicalEditor;
}

const Toolbar = ({ anchorElem, editor }: ToolbarProps): JSX.Element => {
  const [xOffset, setXOffset] = useState(0);
  const nativeSelection = window.getSelection();
  const rootElement = editor.getRootElement();

  useEffect(() => {
    if (nativeSelection === null || rootElement === null) {
      return;
    }

    const rangeRect = getDOMRangeRect(nativeSelection, rootElement);

    setXOffset(rangeRect.x);
  }, [nativeSelection, rootElement]);

  return (
    <Popper anchorEl={anchorElem} open={xOffset !== 0} placement="bottom-start">
      <Paper sx={{ marginLeft: `calc(${xOffset}px - 8px)` }}>
        <Typography>heyyyyyyyy</Typography>
      </Paper>
    </Popper>
  );
};

const useFloatingTextFormatToolbar = ({
  editor,
  anchorElem
}: ToolbarProps): JSX.Element | null => {
  const [isText, setIsText] = useState(false);

  const isInsertingLink = useAtomValue(isInsertingLinkAtom);

  const updatePopup = useCallback(() => {
    editor.getEditorState().read(() => {
      if (editor.isComposing()) {
        return;
      }
      const selection = $getSelection();
      const nativeSelection = window.getSelection();
      const rootElement = editor.getRootElement();

      if (
        nativeSelection !== null &&
        (!$isRangeSelection(selection) ||
          rootElement === null ||
          !rootElement.contains(nativeSelection.anchorNode))
      ) {
        setIsText(false);

        return;
      }

      if (!$isRangeSelection(selection)) {
        return;
      }

      const node = getSelectedNode(selection);

      if (selection.getTextContent() !== '') {
        setIsText($isTextNode(node));
      } else {
        setIsText(false);
      }
    });
  }, [editor]);

  useEffect(() => {
    document.addEventListener('selectionchange', updatePopup);

    return (): void => {
      document.removeEventListener('selectionchange', updatePopup);
    };
  }, [updatePopup]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(() => {
        updatePopup();
      }),
      editor.registerRootListener(() => {
        if (editor.getRootElement() === null) {
          setIsText(false);
        }
      })
    );
  }, [editor, updatePopup]);

  if (!isText) {
    return null;
  }

  return <Toolbar anchorElem={anchorElem} editor={editor} />;
};

const FloatingActionsToolbarPlugin = ({
  anchorElem = document.body
}: FloatingActionsToolbarPluginProps): JSX.Element | null => {
  const [editor] = useLexicalComposerContext();

  return useFloatingTextFormatToolbar({ anchorElem, editor });
};

export default FloatingActionsToolbarPlugin;
