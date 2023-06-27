import { ReactElement } from 'react';

import { ListItemText } from '@mui/material';

type TextProps = {
  primaryText: string;
  secondaryText?: string;
};

export const Text = ({
  primaryText,
  secondaryText
}: TextProps): ReactElement => {
  return <ListItemText primary={primaryText} secondary={secondaryText} />;
};
