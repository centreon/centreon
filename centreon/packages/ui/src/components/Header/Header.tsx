import { ReactElement, ReactNode } from 'react';

import { Typography as MuiTypography } from '@mui/material';

import { useStyles } from './Header.styles';

type HeaderProps = {
  nav?: ReactNode;
  title: string;
};

const Header = ({ title, nav }: HeaderProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <header className={classes.header}>
      <MuiTypography variant="h1">{title}</MuiTypography>
      {nav && <nav>{nav}</nav>}
    </header>
  );
};

export { Header };
