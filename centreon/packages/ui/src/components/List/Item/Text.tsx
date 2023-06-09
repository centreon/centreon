import { ListItemText } from '@mui/material';

type TextProps = {
  primaryText: string;
  secondaryText?: string;
};

export const Text = ({
  primaryText,
  secondaryText
}: TextProps): JSX.Element => {
  return <ListItemText primary={primaryText} secondary={secondaryText} />;
};
