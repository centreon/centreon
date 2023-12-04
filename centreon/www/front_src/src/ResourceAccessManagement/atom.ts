import { atom } from 'jotai';

import { ModalMode, ResourceAccessRuleType } from './models';

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

export const resourceAccessManagementSearchAtom = atom<string>('');

export const selectedRowsAtom = atom<Array<ResourceAccessRuleType>>([]);
export const resourceAccessRulesNamesAtom = atom<
  Array<{ id: number; name: string }>
>([]);
