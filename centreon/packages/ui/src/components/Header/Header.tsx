import React, { ReactElement, ReactNode } from 'react';

import { useStyles } from './Header.styles';

type HeaderProps = {
  nav?: ReactNode;
  title: string;
};

const Header = ({ title, nav }: HeaderProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <header className={classes.header}>
      <h1>{title}</h1>
      {nav && <nav>{nav}</nav>}
    </header>
  );
};

export { Header };
