import { ForwardedRef, ReactElement, ReactNode, forwardRef } from 'react';

import { ListItemProps, ListItem as MuiListItem } from '@mui/material';

import { useStyles } from './ListItem.styles';

type Props = {
  action?: ReactElement;
  children: ReactNode | Array<ReactNode>;
  className?: string;
};

export const ListItem = forwardRef(
  (
    { action, children, className, ...attr }: Props & ListItemProps,
    ref?: ForwardedRef<HTMLLIElement>
  ) => {
    const { classes, cx } = useStyles();

    return (
      <MuiListItem
        disableGutters
        className={cx(classes.listItem, className)}
        ref={ref}
        secondaryAction={
          action && <div className={classes.secondary}>{action}</div>
        }
        {...attr}
      >
        {children}
      </MuiListItem>
    );
  }
);
