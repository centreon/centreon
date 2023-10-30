import { ReactElement, ReactNode } from 'react';

import { MenuProvider } from './useMenu';

type MenuProps = {
  children?: ReactNode | Array<ReactNode>;
  isOpen?: boolean;
  onClose?: () => void;
  onOpen?: () => void;
};

const Menu = ({
  children,
  isOpen = false,
  onOpen,
  onClose
}: MenuProps): ReactElement => {
  return (
    <MenuProvider initialIsOpen={isOpen} onClose={onClose} onOpen={onOpen}>
      {children}
    </MenuProvider>
  );
};

export { Menu };
