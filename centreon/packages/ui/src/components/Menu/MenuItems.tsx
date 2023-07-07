import { ReactElement, ReactNode } from 'react';

import { Menu as MuiMenu } from '@mui/material';

import { useStyles } from './Menu.styles';
import { useMenu } from './useMenu';

type MenuItemsProps = {
  children?: ReactNode | Array<ReactNode>;
};
const MenuItems = ({ children }: MenuItemsProps): ReactElement => {
  const { classes } = useStyles();

  const { isMenuOpen, setIsMenuOpen, anchorEl, onClose } = useMenu();

  const onCloseMenu = (): void => {
    setIsMenuOpen(false);
    onClose?.();
  };

  return (
    <MuiMenu
      anchorEl={anchorEl}
      className={classes.menuItems}
      open={isMenuOpen}
      variant="menu"
      onClick={onCloseMenu}
      onClose={onCloseMenu}
    >
      {children}
    </MuiMenu>
  );
};

export { MenuItems };
