import { ReactElement, ReactNode } from 'react';

import { MenuItem as MuiMenuItem } from '@mui/material';

import { useStyles } from './Menu.styles';

type MenuItemProps = {
  children?: ReactNode;
  isActive?: boolean;
  isDisabled?: boolean;
  key?: string;
  onClick?: () => void;
};

const MenuItem = ({
  children,
  onClick,
  key,
  isActive = false,
  isDisabled = false
}: MenuItemProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiMenuItem
      className={classes.menuItem}
      data-is-active={isActive}
      data-is-disabled={isDisabled}
      disabled={isDisabled}
      key={key}
      selected={isActive}
      onClick={() => onClick?.()}
    >
      {children}
    </MuiMenuItem>
  );
};

export { MenuItem };
