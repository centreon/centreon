import { ReactElement, ReactNode } from 'react';

import { MenuItem as MuiMenuItem } from '@mui/material';

import { useStyles } from './Menu.styles';

type MenuItemProps = {
  children?: ReactNode;
  className?: string;
  isActive?: boolean;
  isDisabled?: boolean;
  onClick?: () => void;
};

const MenuItem = ({
  children,
  onClick,
  isActive = false,
  isDisabled = false,
  className
}: MenuItemProps): ReactElement => {
  const { cx, classes } = useStyles();

  return (
    <MuiMenuItem
      className={cx(classes.menuItem, className)}
      data-is-active={isActive}
      data-is-disabled={isDisabled}
      disabled={isDisabled}
      selected={isActive}
      onClick={() => onClick?.()}
    >
      {children}
    </MuiMenuItem>
  );
};

export { MenuItem };
