import { makeStyles } from 'tss-react/mui';

import { Divider } from '@mui/material';

import FormatButtons from './FormatButtons';
import UndoRedoButtons from './UndoRedoButtons';
import MacrosButton from './MacrosButton';
import BlockButtons from './BlockButtons';

interface Props {
  disabled: boolean;
  displayMacrosButton?: boolean;
  editable: boolean;
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
  editable,
  displayMacrosButton,
  disabled
}: Props): JSX.Element | null => {
  const { classes } = useStyles();

  return editable ? (
    <div className={classes.container}>
      <UndoRedoButtons disabled={disabled} />
      <Divider flexItem orientation="vertical" />
      <BlockButtons disabled={disabled} />
      <Divider flexItem orientation="vertical" />
      <FormatButtons disabled={disabled} />
      {displayMacrosButton && (
        <>
          <Divider flexItem orientation="vertical" />
          <MacrosButton disabled={disabled} />
        </>
      )}
    </div>
  ) : null;
};

export default ToolbarPlugin;
