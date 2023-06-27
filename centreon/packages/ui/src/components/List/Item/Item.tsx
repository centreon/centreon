import { ForwardedRef, forwardRef, ReactElement } from 'react';

import { ListItem } from '@mui/material';

type ItemProps = {
  action?: ReactElement;
  children: ReactElement | Array<ReactElement>;
};

export const Item = forwardRef(
  ({ action, children }: ItemProps, ref?: ForwardedRef<HTMLLIElement>) => {
    return (
      <ListItem disableGutters ref={ref} secondaryAction={action}>
        {children}
      </ListItem>
    );
  }
);
