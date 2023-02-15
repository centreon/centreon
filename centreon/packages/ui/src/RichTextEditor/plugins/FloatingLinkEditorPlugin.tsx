import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  LexicalEditor
} from 'lexical';
import { mergeRegister } from '@lexical/utils';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { $isLinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link';
import { useTranslation } from 'react-i18next';

import { Popper, IconButton, Paper, Link, Box, Divider } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';

import { getSelectedNode } from '../utils/getSelectedNode';
import { getDOMRangeRect } from '../utils/getDOMRangeRect';
import { editLinkModeAtom, isInsertingLinkAtom, linkValueAtom } from '../atoms';
import InputField from '../../InputField/Text';
import {
  labelInputLink,
  labelSavedLink,
  labelEditLink
} from '../translatedLabels';

interface UseFloatingLinkEditorProps {
  editor: LexicalEditor;
}

interface FloatingLinkEditorProps {
  editor: LexicalEditor;
}

const FloatingLinkEditor = ({
  editor
}: FloatingLinkEditorProps): JSX.Element | null => {
  const nativeSelection = window.getSelection();
  const rootElement = editor.getRootElement();
  const { t } = useTranslation();

  const [editMode, setEditMode] = useAtom(editLinkModeAtom);
  const linkUrl = useAtomValue(linkValueAtom);

  if (nativeSelection === null || rootElement === null) {
    return null;
  }

  const rangeRect = getDOMRangeRect(nativeSelection, rootElement);

  const xOffset = rangeRect.x - (rootElement?.getBoundingClientRect()?.x || 0);
  const yOffset =
    rangeRect.y - (rootElement?.getBoundingClientRect()?.y || 0) + 50;

  return (
    <Popper open anchorEl={rootElement} placement="top-start">
      <Paper
        sx={{
          transform: `translate3d(${xOffset}px, ${
            editMode ? yOffset + 10 : yOffset
          }px, 0px)`
        }}
      >
        {editMode ? (
          <InputField
            autoFocus
            defaultValue={linkUrl}
            label={t(labelInputLink)}
            size="small"
            onBlur={(event): void => {
              const { value } = event.target;

              event.preventDefault();

              if (value !== '') {
                editor.dispatchCommand(TOGGLE_LINK_COMMAND, value);
              }
              setEditMode(false);
            }}
            onKeyDown={(event): void => {
              const { value } = event.target;
              if (event.key === 'Enter') {
                event.preventDefault();

                if (value !== '') {
                  editor.dispatchCommand(TOGGLE_LINK_COMMAND, value);
                }
                setEditMode(false);
              } else if (event.key === 'Escape') {
                event.preventDefault();
                setEditMode(false);
              }
            }}
          />
        ) : (
          <Box component="span" sx={{ margin: '10px' }}>
            <Link
              aria-label={labelSavedLink}
              href={linkUrl}
              rel="noreferrer"
              target="_blank"
              variant="button"
            >
              {linkUrl}
            </Link>
            <IconButton
              aria-label={labelEditLink}
              size="small"
              sx={{ marginLeft: '5px' }}
              onClick={(): void => setEditMode(true)}
            >
              <EditIcon fontSize="small" />
            </IconButton>
          </Box>
        )}
      </Paper>
    </Popper>
  );
};

const useFloatingTextFormatToolbar = ({
  editor
}: UseFloatingLinkEditorProps): JSX.Element | null => {
  const [isText, setIsText] = useState(false);
  const isInsertingLink = useAtomValue(isInsertingLinkAtom);
  const editLinkMode = useAtomValue(editLinkModeAtom);
  const setLinkUrl = useSetAtom(linkValueAtom);

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
          !rootElement.contains(nativeSelection.anchorNode)) &&
        !editLinkMode
      ) {
        setIsText(false);

        return;
      }

      if (!$isRangeSelection(selection) || editLinkMode) {
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
        setIsText($isTextNode(node) || $isLinkNode(node));
      } else {
        setIsText(false);
      }
    });
  }, [editor, editLinkMode]);

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

  return <FloatingLinkEditor editor={editor} />;
};

const FloatingActionsToolbarPlugin = (): JSX.Element | null => {
  const [editor] = useLexicalComposerContext();

  return useFloatingTextFormatToolbar({ editor });
};

export default FloatingActionsToolbarPlugin;
