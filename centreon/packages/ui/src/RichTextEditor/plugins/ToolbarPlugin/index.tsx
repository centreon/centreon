import { EditorState } from 'lexical';
import { makeStyles } from 'tss-react/mui';

import { Divider } from '@mui/material';

import FormatButtons from './FormatButtons';
import UndoRedoButtons from './UndoRedoButtons';
import MacrosButton from './MacrosButton';

interface Props {
  displayMacrosButton?: boolean;
  editable: boolean;
  getEditorState?: (editorState: EditorState) => void;
}

export const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    marginBottom: theme.spacing(1)
  },
  macros: {
    '& span': {
      fontSize: theme.typography.caption.fontSize
    }
  },
  macrosButton: {
    marginBottom: theme.spacing(0.5)
  }
}));

const ToolbarPlugin = ({
  getEditorState,
  editable,
  displayMacrosButton
}: Props): JSX.Element | null => {
  const { classes } = useStyles();

  return editable ? (
    <div className={classes.container}>
      <UndoRedoButtons />
      <Divider flexItem orientation="vertical" />
      <FormatButtons getEditorState={getEditorState} />
      {displayMacrosButton && (
        <>
          <Divider flexItem orientation="vertical" />
          <MacrosButton />
        </>
      )}
    </div>
  ) : null;
};

export default ToolbarPlugin;
