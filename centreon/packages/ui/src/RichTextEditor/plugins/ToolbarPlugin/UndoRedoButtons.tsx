import { useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  UNDO_COMMAND,
  REDO_COMMAND,
  CAN_UNDO_COMMAND,
  CAN_REDO_COMMAND
} from 'lexical';
import { mergeRegister } from '@lexical/utils';
import { useTranslation } from 'react-i18next';

import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';

import { IconButton } from '../../..';
import { labelRedo, labelUndo } from '../../translatedLabels';

const LowPriority = 1;

const UndoRedoButtons = (): JSX.Element => {
  const { t } = useTranslation();

  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);

  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    return mergeRegister(
      editor.registerCommand(
        CAN_UNDO_COMMAND,
        (payload) => {
          setCanUndo(payload);

          return false;
        },
        LowPriority
      ),
      editor.registerCommand(
        CAN_REDO_COMMAND,
        (payload) => {
          setCanRedo(payload);

          return false;
        },
        LowPriority
      )
    );
  }, []);

  const undo = (): void => {
    editor.dispatchCommand(UNDO_COMMAND, undefined);
  };

  const redo = (): void => {
    editor.dispatchCommand(REDO_COMMAND, undefined);
  };

  const translatedUndo = t(labelUndo);
  const translatedRedo = t(labelRedo);

  return (
    <>
      <IconButton
        ariaLabel={translatedUndo}
        disabled={!canUndo}
        title={translatedUndo}
        tooltipPlacement="top"
        onClick={undo}
      >
        <UndoIcon />
      </IconButton>
      <IconButton
        ariaLabel={translatedRedo}
        disabled={!canRedo}
        title={translatedRedo}
        tooltipPlacement="top"
        onClick={redo}
      >
        <RedoIcon />
      </IconButton>
    </>
  );
};

export default UndoRedoButtons;
