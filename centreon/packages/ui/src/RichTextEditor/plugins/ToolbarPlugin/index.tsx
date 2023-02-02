import { EditorState } from 'lexical';
import { makeStyles } from 'tss-react/mui';

import { Divider } from '@mui/material';

import FormatButtons from './FormatButtons';
import UndoRedoButtons from './UndoRedoButtons';

interface Props {
  getEditorState?: (editorState: EditorState) => void;
}

const useStyles = makeStyles()((theme) => ({
  container: {
    columnGap: theme.spacing(1),
    display: 'flex',
    marginBottom: theme.spacing(1)
  }
}));

const ToolbarPlugin = ({ getEditorState }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <UndoRedoButtons />
      <Divider flexItem orientation="vertical" />
      <FormatButtons getEditorState={getEditorState} />
    </div>
  );
};

export default ToolbarPlugin;
