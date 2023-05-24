import { ReactElement, ReactNode } from 'react';

import { Typography } from '@mui/material';

import { useStyles } from './Header.styles';

type HeaderProps = {
  nav?: ReactNode;
  title: string;
};

const Header = ({ title, nav }: HeaderProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <header>
      <div className={classes.header}>
        <Typography variant="h5">{title}</Typography>
        {nav && <nav>{nav}</nav>}
      </div>
    </header>
  );
};

export { Header };
