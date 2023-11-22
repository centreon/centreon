import { atom } from 'jotai';

import { ModalMode } from '../models';

export const resourceAccessRuleModalModeAtom = atom<ModalMode>(
  ModalMode.Create
);
export const editedResourceAccessRuleIdAtom = atom<number | null>(null);

export const modalStateAtom = atom<{
  isOpen: boolean;
  mode: ModalMode;
}>({
  isOpen: false,
  mode: ModalMode.Create
});
