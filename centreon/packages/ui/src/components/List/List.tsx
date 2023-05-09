import React, { ReactNode } from 'react';
import { useStyles } from './List.styles';


type ListProps = {
  children: ReactNode;
  variant?: 'grid';
};

const List: React.FC<ListProps> = ({
  children,
  variant = 'grid',
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div
      className={classes.list}
      data-variant={variant}
    >
      {children}
    </div>
  );
};

export { List };