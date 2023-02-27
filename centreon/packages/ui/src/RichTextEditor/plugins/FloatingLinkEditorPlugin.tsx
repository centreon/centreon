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
import { equals, isNil } from 'ramda';

import { Popper, IconButton, Paper, Link, Box } from '@mui/material';
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

interface FloatingLinkEditorPluginProps {
  editable: boolean;
}

interface UseFloatingLinkEditorProps extends FloatingLinkEditorPluginProps {
  editor: LexicalEditor;
}

interface FloatingLinkEditorProps {
  editor: LexicalEditor;
}

interface TooltipPosition {
  x: number;
  y: number;
}

const FloatingLinkEditor = ({
  editor
}: FloatingLinkEditorProps): JSX.Element | null => {
  const nativeSelection = window.getSelection();
  const rootElement = editor.getRootElement();
  const { t } = useTranslation();
  const [tooltipPosition, setTooltipPosition] = useState<TooltipPosition>({
    x: 0,
    y: 0
  });

  const [editMode, setEditMode] = useAtom(editLinkModeAtom);
  const [linkUrl, setLinkUrl] = useAtom(linkValueAtom);

  const rangeRect = getDOMRangeRect(nativeSelection, rootElement);

  const acceptOrCancelNewLinkValue = useCallback(
    (event): void => {
      const { value } = event.target;
      if (event.key === 'Enter') {
        event.preventDefault();

        if (value !== '') {
          editor.dispatchCommand(TOGGLE_LINK_COMMAND, {
            target: '_blank',
            url: value
          });
        }
        setEditMode(false);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        setEditMode(false);
      }
    },
    [setEditMode, setLinkUrl]
  );

  useEffect(() => {
    if (isNil(rangeRect)) {
      return;
    }

    const isNotPositioned = equals(rangeRect.x, 0) && equals(rangeRect.y, 0);

    if (isNotPositioned || !nativeSelection) {
      return;
    }

    const nodeX = rangeRect.x;
    const nodeY = rangeRect.y;
    const nodeHeight = rangeRect.height;

    setTooltipPosition({ x: nodeX, y: nodeY + nodeHeight });
  }, [rangeRect?.x, rangeRect?.y]);

  if (isNil(rangeRect)) {
    return null;
  }

  const xOffset =
    tooltipPosition.x - (rootElement?.getBoundingClientRect()?.x || 0);

  const rootElementY = rootElement?.getBoundingClientRect()?.y || 0;
  const yOffset = tooltipPosition.y - rootElementY + 30;

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
            onKeyDown={acceptOrCancelNewLinkValue}
          />
        ) : (
          <Box component="span" sx={{ margin: '10px' }}>
            <Link
              aria-label={labelSavedLink}
              href={linkUrl}
              rel="noreferrer noopener"
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
  editor,
  editable
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

  if (!editable || !isText || !isInsertingLink) {
    return null;
  }

  return <FloatingLinkEditor editor={editor} />;
};

const FloatingActionsToolbarPlugin = ({
  editable
}: FloatingLinkEditorPluginProps): JSX.Element | null => {
  const [editor] = useLexicalComposerContext();

  return useFloatingTextFormatToolbar({ editable, editor });
};

export default FloatingActionsToolbarPlugin;
