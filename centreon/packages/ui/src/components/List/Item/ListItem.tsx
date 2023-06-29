import { ForwardedRef, forwardRef, ReactElement, ReactNode } from 'react';

import { ListItem as MuiListItem } from '@mui/material';

import { useStyles } from './ListItem.styles';

type ListItemProps = {
  action?: ReactElement;
  children: ReactNode | Array<ReactNode>;
};

export const ListItem = forwardRef(
  ({ action, children }: ListItemProps, ref?: ForwardedRef<HTMLLIElement>) => {
    const { classes } = useStyles();

    return (
      <MuiListItem
        disableGutters
        className={classes.listItem}
        ref={ref}
        secondaryAction={action}
      >
        {children}
      </MuiListItem>
    );
  }
);
