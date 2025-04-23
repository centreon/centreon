import { ReactElement, ReactNode, useRef } from 'react';

import {
  Provider,
  atom,
  createStore,
  useAtom,
  useAtomValue,
  useStore
} from 'jotai';
import { useHydrateAtoms } from 'jotai/utils';

/** state */

const isMenuOpenAtom = atom(false);
const anchorElAtom = atom<null | HTMLElement>(null);
const onOpenAtom = atom<(() => void) | null>(null);
const onCloseAtom = atom<(() => void) | null>(null);

/** provider */

type MenuProviderProps = {
  children: ReactNode;
  initialIsOpen?: boolean;
  onClose?: () => void;
  onOpen?: () => void;
};

const MenuProvider = ({
  children,
  initialIsOpen,
  onOpen,
  onClose
}: MenuProviderProps): ReactElement => {
  const menuStore = useRef(createStore()).current;

  useHydrateAtoms(
    [
      [isMenuOpenAtom, initialIsOpen ?? false],
      [anchorElAtom, null],
      [onOpenAtom, onOpen],
      [onCloseAtom, onClose]
    ],
    { store: menuStore }
  );

  return <Provider store={menuStore}>{children}</Provider>;
};

/** hook */

type UseMenu = {
  anchorEl: null | HTMLElement;
  isMenuOpen: boolean;
  onClose: (() => void) | null;
  onOpen: (() => void) | null;
  setAnchorEl: (event: null | HTMLElement) => void;
  setIsMenuOpen: (isOpen: boolean | ((currentIsMenuOpen) => boolean)) => void;
};

const useMenu = (): UseMenu => {
  const store = useStore();

  const [isMenuOpen, setIsMenuOpen] = useAtom(isMenuOpenAtom, { store });
  const [anchorEl, setAnchorEl] = useAtom(anchorElAtom, { store });
  const onOpen = useAtomValue(onOpenAtom, { store });
  const onClose = useAtomValue(onCloseAtom, { store });

  return {
    anchorEl,
    isMenuOpen,
    onClose,
    onOpen,
    setAnchorEl,
    setIsMenuOpen
  };
};

export { useMenu, MenuProvider };
