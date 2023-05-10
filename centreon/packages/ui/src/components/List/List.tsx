import React, { ReactNode } from 'react';

import { useStyles } from './List.styles';

interface ListProps {
  children: ReactNode;
  isEmpty?: boolean;
  variant?: 'grid';
}

const List: React.FC<ListProps> = ({
  children,
  variant = 'grid',
  isEmpty = false
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div
      className={classes.list}
      data-is-empty={isEmpty}
      data-variant={variant}
    >
      {children}
    </div>
  );
};

export { List };
