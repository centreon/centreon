import { ReactElement, ReactNode } from 'react';

import { ListItemText as MuiListItemText } from '@mui/material';

import { useStyles } from './ListItem.styles';

type TextProps = {
  className?: string;
  primaryText: ReactNode;
  secondaryText?: ReactNode;
};

export const Text = ({
  primaryText,
  secondaryText,
  className
}: TextProps): ReactElement => {
  const { classes, cx } = useStyles();

  return (
    <MuiListItemText
      className={cx(classes.text, className)}
      primary={primaryText}
      secondary={secondaryText}
    />
  );
};
