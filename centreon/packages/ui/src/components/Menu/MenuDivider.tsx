import { ReactElement } from 'react';

import { Divider as MuiDivider } from '@mui/material';

import { useStyles } from './Menu.styles';

type MenuDividerProps = {
  key?: string;
};

const MenuDivider = ({ key }: MenuDividerProps): ReactElement => {
  const { classes } = useStyles();

  return <MuiDivider className={classes.menuDivider} key={key} />;
};

export { MenuDivider };
