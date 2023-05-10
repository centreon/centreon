import React, { ReactNode } from 'react';

import { useStyles } from './Header.styles';

interface HeaderProps {
  nav?: ReactNode;
  title: string;
}

const Header: React.FC<HeaderProps> = ({ title, nav }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <header className={classes.header}>
      <h1>{title}</h1>
      {nav && <nav>{nav}</nav>}
    </header>
  );
};

export { Header };
