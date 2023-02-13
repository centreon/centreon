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
import { $isLinkNode } from '@lexical/link';

import { Paper, Popper, TextField, Popover, IconButton } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';

import { getSelectedNode } from '../utils/getSelectedNode';
import { getDOMRangeRect } from '../utils/getDOMRangeRect';
import { isInsertingLinkAtom } from '../atoms';
import InputField from '../../InputField/Text';
import { labelInputLink } from '../translatedLabels';

interface FloatingActionsToolbarPluginProps {
  anchorElem: HTMLElement;
}

interface FloatingTextFormatToolbarProps
  extends FloatingActionsToolbarPluginProps {
  editor: LexicalEditor;
}

interface ToolbarProps extends FloatingActionsToolbarPluginProps {
  editor: LexicalEditor;
  linkUrl: string;
  setLinkUrl: (url: string) => void;
}

const Toolbar = ({
  anchorElem,
  editor,
  linkUrl,
  setLinkUrl
}: ToolbarProps): JSX.Element => {
  const [xOffset, setXOffset] = useState(0);
  const nativeSelection = window.getSelection();
  const rootElement = editor.getRootElement();
  const [editMode, setEditMode] = useState(false);

  useEffect(() => {
    if (nativeSelection === null || rootElement === null) {
      return;
    }

    const rangeRect = getDOMRangeRect(nativeSelection, rootElement);
    console.log('selection', nativeSelection);
    setXOffset(rangeRect.x);
  }, [nativeSelection, rootElement]);
  console.log('offset', xOffset);

  return (
    <Popper
      anchorEl={anchorElem}
      // anchorOrigin={{ horizontal: 'left', vertical: 'bottom' }}
      open={xOffset !== 0}
    >
      <TextField
        InputProps={{
          disableUnderline: true
        }}
        defaultValue={linkUrl}
        id="insert-link"
        variant="standard"
        // label={labelInputLink}
        onChange={(event: React.ChangeEvent<HTMLInputElement>): void => {
          setLinkUrl(event.target.value);
        }}
      />
      <IconButton
        aria-label="delete"
        onClick={(): void => setEditMode(!editMode)}
      >
        <EditIcon />
      </IconButton>
    </Popper>
  );
};

const useFloatingTextFormatToolbar = ({
  editor,
  anchorElem
}: FloatingTextFormatToolbarProps): JSX.Element | null => {
  const [isText, setIsText] = useState(false);
  const [linkUrl, setLinkUrl] = useState('');
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
      const parent = node.getParent();
      if ($isLinkNode(parent)) {
        setLinkUrl(parent.getURL());
      } else if ($isLinkNode(node)) {
        setLinkUrl(node.getURL());
      } else {
        setLinkUrl('');
      }

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

  if (!isText || !isInsertingLink) {
    return null;
  }

  return (
    <Toolbar
      anchorElem={anchorElem}
      editor={editor}
      linkUrl={linkUrl}
      setLinkUrl={setLinkUrl}
    />
  );
};

const FloatingActionsToolbarPlugin = ({
  anchorElem = document.body
}: FloatingActionsToolbarPluginProps): JSX.Element | null => {
  const [editor] = useLexicalComposerContext();

  return useFloatingTextFormatToolbar({ anchorElem, editor });
};

export default FloatingActionsToolbarPlugin;
