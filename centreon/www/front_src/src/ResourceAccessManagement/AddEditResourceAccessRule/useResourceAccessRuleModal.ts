import { useAtom } from 'jotai';

import { ModalMode } from '../models';
import { modalStateAtom } from '../atom';

type UseResourceAccessRuleConfig = {
  closeModal: () => void;
  createResourceAccessRule: () => void;
  isModalOpen: boolean;
  mode: ModalMode;
};

const useResourceAccessRuleConfig = (): UseResourceAccessRuleConfig => {
  const [modalState, setModalState] = useAtom(modalStateAtom);

  const closeModal = (): void => {
    setModalState({ ...modalState, isOpen: false });
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
