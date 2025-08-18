import { useAtom } from 'jotai';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router';

import { modalStateAtom, tokenAtom } from '../atoms';
import { TokenType } from '../models';

interface UseModalState {
  close: () => void;
  isOpen: boolean;
}

const useModal = (): UseModalState => {
  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const [modalState, setModalState] = useAtom(modalStateAtom);
  const [token, setToken] = useAtom(tokenAtom);

  useEffect(() => {
    const mode = searchParams.get('mode');
    const type = searchParams.get('type');

    if (mode) {
      setModalState({
        isOpen: true,
        mode: 'add',
        type: (type as TokenType) || TokenType.API
      });
    }
  }, [searchParams, setModalState]);

  const reset = (): void => {
    setSearchParams({});
    setModalState({ ...modalState, isOpen: false, type: TokenType.API });
  };

  const close = () => {
    if (token) {
      setToken(null);
    }

    reset();
  };

  return {
    close,
    isOpen: modalState.isOpen
  };
};

export default useModal;
