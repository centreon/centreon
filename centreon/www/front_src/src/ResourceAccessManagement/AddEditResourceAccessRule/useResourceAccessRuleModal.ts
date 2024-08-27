import { useAtom } from 'jotai';

import { modalStateAtom } from '../atom';
import { ModalMode } from '../models';

type UseResourceAccessRuleConfig = {
  closeModal: () => void;
  createResourceAccessRule: () => void;
  isModalOpen: boolean;
  mode: ModalMode;
};

const useResourceAccessRuleConfig = (): UseResourceAccessRuleConfig => {
  const [modalState, setModalState] = useAtom(modalStateAtom);

  const closeModal = (): void => {
    setModalState({ isOpen: false, mode: ModalMode.Create });
  };

  const createResourceAccessRule = (): void => {
    setModalState({
      ...modalState,
      isOpen: true
    });
  };

  return {
    closeModal,
    createResourceAccessRule,
    isModalOpen: modalState.isOpen,
    mode: modalState.mode
  };
};

export default useResourceAccessRuleConfig;
