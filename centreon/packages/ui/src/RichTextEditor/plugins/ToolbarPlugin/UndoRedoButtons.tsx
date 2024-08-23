import { useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { mergeRegister } from '@lexical/utils';
import {
  CAN_REDO_COMMAND,
  CAN_UNDO_COMMAND,
  REDO_COMMAND,
  UNDO_COMMAND
} from 'lexical';
import { useTranslation } from 'react-i18next';

import RedoIcon from '@mui/icons-material/Redo';
import UndoIcon from '@mui/icons-material/Undo';

import { IconButton } from '../../..';
import { labelRedo, labelUndo } from '../../translatedLabels';

import { useStyles } from './ToolbarPlugin.styles';

const LowPriority = 1;

interface Props {
  disabled: boolean;
}

const UndoRedoButtons = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();

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
        className={classes.button}
        disabled={!canUndo || disabled}
        title={translatedUndo}
        tooltipPlacement="top"
        onClick={undo}
      >
        <UndoIcon />
      </IconButton>
      <IconButton
        ariaLabel={translatedRedo}
        className={classes.button}
        disabled={!canRedo || disabled}
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
