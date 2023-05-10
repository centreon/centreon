import React, { ReactNode } from 'react';
import { useStyles } from './List.styles';


type ListProps = {
  children: ReactNode;
  variant?: 'grid';
  isEmpty?: boolean;
};

const List: React.FC<ListProps> = ({
  children,
  variant = 'grid',
  isEmpty = false
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div
      className={classes.list}
      data-variant={variant}
      data-is-empty={isEmpty}
    >
      {children}
    </div>
  );
};

export { List };