import { ReactNode } from 'react';

import { List as MUIList } from '@mui/material';

import { useListStyles } from './List.styles';

type Props = {
  children: ReactNode;
};

export const List = ({ children }: Props): JSX.Element => {
  const { classes } = useListStyles();

  return (
    <MUIList dense className={classes.list}>
      {children}
    </MUIList>
  );
};
