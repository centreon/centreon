import { ForwardedRef, forwardRef, ReactElement, ReactNode } from 'react';

import { ListItem as MuiListItem } from '@mui/material';

import { useStyles } from './ListItem.styles';

type ListItemProps = {
  action?: ReactElement;
  children: ReactNode | Array<ReactNode>;
  className?: string;
};

export const ListItem = forwardRef(
  (
    { action, children, className, ...attr }: ListItemProps,
    ref?: ForwardedRef<HTMLLIElement>
  ) => {
    const { classes, cx } = useStyles();

    return (
      <MuiListItem
        disableGutters
        className={cx(classes.listItem, className)}
        ref={ref}
        secondaryAction={action}
        {...attr}
      >
        {children}
      </MuiListItem>
    );
  }
);
