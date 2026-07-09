import { List, ListItemButton, ListItemText } from '@mui/material';

import type { PredefinedRowSelection } from '../../models';

interface Props {
  close: () => void;
  onSelectRowsWithCondition: (
    condition: (row: Record<string, unknown>) => boolean
  ) => void;
  predefinedRowsSelection: Array<PredefinedRowSelection>;
}

const PredefinedSelectionList = ({
  close,
  predefinedRowsSelection,
  onSelectRowsWithCondition
}: Props): JSX.Element => (
  <List dense>
    {predefinedRowsSelection.map(({ label, rowCondition }) => {
      const onSelectionClick = (): void => {
        onSelectRowsWithCondition(rowCondition);
        close();
      };

      return (
        <ListItemButton key={label} onClick={onSelectionClick}>
          <ListItemText>{label}</ListItemText>
        </ListItemButton>
      );
    })}
  </List>
);

export default PredefinedSelectionList;
