import { EditorState } from 'lexical';
import { makeStyles } from 'tss-react/mui';

import { Divider } from '@mui/material';

import FormatButtons from './FormatButtons';
import UndoRedoButtons from './UndoRedoButtons';

interface Props {
  editable: boolean;
  getEditorState?: (editorState: EditorState) => void;
}

const useStyles = makeStyles()((theme) => ({
  container: {
    columnGap: theme.spacing(1),
    display: 'flex',
    marginBottom: theme.spacing(1)
  }
}));

const ToolbarPlugin = ({
  getEditorState,
  editable
}: Props): JSX.Element | null => {
  const { classes } = useStyles();

  return editable ? (
    <div className={classes.container}>
      <UndoRedoButtons />
      <Divider flexItem orientation="vertical" />
      <FormatButtons getEditorState={getEditorState} />
    </div>
  ) : null;
};

export default ToolbarPlugin;
