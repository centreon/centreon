import { ListItemText } from '@mui/material';

type ItemTextProps = {
  primaryText: string;
  secondaryText?: string;
};

export const ItemText = ({
  primaryText,
  secondaryText
}: ItemTextProps): JSX.Element => {
  return <ListItemText primary={primaryText} secondary={secondaryText} />;
};
