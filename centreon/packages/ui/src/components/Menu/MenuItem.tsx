import { MenuItem as MuiMenuItem } from '@mui/material';

import type { ReactElement, ReactNode } from 'react';

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
  return (
    <MuiMenuItem
      className={className}
      data-is-active={isActive}
      data-is-disabled={isDisabled}
      disabled={isDisabled}
      onClick={() => onClick?.()}
      selected={isActive}
    >
      {children}
    </MuiMenuItem>
  );
};

export { MenuItem };
