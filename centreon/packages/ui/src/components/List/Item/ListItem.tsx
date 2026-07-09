import { type ListItemProps, ListItem as MuiListItem } from '@mui/material';

import {
  type ForwardedRef,
  forwardRef,
  type ReactElement,
  type ReactNode
} from 'react';

import { useStyles } from './ListItem.styles';

interface Props {
  action?: ReactElement;
  children: ReactNode | Array<ReactNode>;
  className?: string;
}

export const ListItem = forwardRef(
  (
    { action, children, className, ...attr }: Props & ListItemProps,
    ref?: ForwardedRef<HTMLLIElement>
  ) => {
    const { classes, cx } = useStyles();

    return (
      <MuiListItem
        className={cx(classes.listItem, className)}
        disableGutters
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
