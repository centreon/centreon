import { makeStyles } from 'tss-react/mui';

import { Divider } from '@mui/material';

import AlignPicker from './AlignPicker';
import BlockButtons from './BlockButtons';
import FormatButtons from './FormatButtons';
import LinkButton from './LinkButton';
import ListButton from './ListButton';
import MacrosButton from './MacrosButton';
import UndoRedoButtons from './UndoRedoButtons';

interface Props {
  className?: string;
  disabled: boolean;
  displayBlockButtons: boolean;
  displayMacrosButton?: boolean;
  editable: boolean;
}

export const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    marginBottom: theme.spacing(1)
  }
}));

const ToolbarPlugin = ({
  editable,
  displayMacrosButton,
  disabled,
  displayBlockButtons,
  className
}: Props): JSX.Element | null => {
  const { cx, classes } = useStyles();

  return editable ? (
    <div className={cx(classes.container, className)}>
      <UndoRedoButtons disabled={disabled} />
      {displayBlockButtons && (
        <>
          <Divider flexItem orientation="vertical" />
          <BlockButtons disabled={disabled} />
        </>
      )}
      <FormatButtons disabled={disabled} />
      <AlignPicker disabled={disabled} />
      <ListButton disabled={disabled} />
      <LinkButton disabled={disabled} />
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
