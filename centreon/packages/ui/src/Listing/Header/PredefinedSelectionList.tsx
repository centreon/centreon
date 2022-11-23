import { List, ListItem, ListItemText } from '@mui/material';

import { PredefinedRowSelection } from '../models';

interface Props {
  close: () => void;
  onSelectRowsWithCondition: (condition) => void;
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
        <ListItem button key={label} onClick={onSelectionClick}>
          <ListItemText>{label}</ListItemText>
        </ListItem>
      );
    })}
  </List>
);

export default PredefinedSelectionList;
