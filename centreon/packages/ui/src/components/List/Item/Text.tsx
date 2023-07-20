import { ReactElement, ReactNode } from 'react';

import { ListItemText as MuiListItemText } from '@mui/material';

import { useStyles } from './ListItem.styles';

type TextProps = {
  primaryText: ReactNode;
  secondaryText?: ReactNode;
};

export const Text = ({
  primaryText,
  secondaryText
}: TextProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiListItemText
      className={classes.text}
      primary={primaryText}
      secondary={secondaryText}
    />
  );
};
