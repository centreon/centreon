import { ReactElement, ReactNode } from 'react';

import { List as MuiList } from '@mui/material';

import { useStyles } from './List.styles';

interface Props {
  children: ReactNode;
}

export const List = ({ children }: Props): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiList dense className={classes.list}>
      {children}
    </MuiList>
  );
};
