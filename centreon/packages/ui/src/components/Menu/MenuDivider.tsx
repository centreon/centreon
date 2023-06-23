import { ReactElement } from 'react';

import { Divider as MuiDivider } from '@mui/material';

import { useStyles } from './Menu.styles';

const MenuDivider = (): ReactElement => {
  const { classes } = useStyles();

  return <MuiDivider className={classes.menuDivider} />;
};

export { MenuDivider };
