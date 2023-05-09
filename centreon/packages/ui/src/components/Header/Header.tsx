import React, { ReactNode } from 'react';
import { useStyles } from './Header.styles';


type HeaderProps = {
  title: String;
  nav?: ReactNode;
};

const Header: React.FC<HeaderProps> = ({
  title,
  nav
}): JSX.Element => {
  const {classes} = useStyles();

  return (
    <header className={classes.header}>
      <h1>{title}</h1>
      {nav && <nav>{nav}</nav>}
    </header>
  );
};

export { Header };