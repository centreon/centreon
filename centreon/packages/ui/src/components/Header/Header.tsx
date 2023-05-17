import React, { ReactNode } from 'react';

import { Divider, Typography } from '@mui/material';

import { useStyles } from './Header.styles';

interface HeaderProps {
  nav?: ReactNode;
  title: string;
}

const Header = ({ title, nav }: HeaderProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <header>
      <div className={classes.header}>
        <Typography variant="h5">{title}</Typography>
        {nav && <nav>{nav}</nav>}
      </div>
      <Divider className={classes.divider} orientation="horizontal" />
    </header>
  );
};

export { Header };
