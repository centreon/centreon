import { Divider } from '@mui/material';

import { ReactElement } from 'react';
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

const ToolbarPlugin = ({
  editable,
  displayMacrosButton,
  disabled,
  displayBlockButtons,
  className
}: Props): ReactElement | null => {
  return editable ? (
    <div
      className={`flex items-center gap-2 mb-2 overflow-y-auto ${className}`}
    >
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
