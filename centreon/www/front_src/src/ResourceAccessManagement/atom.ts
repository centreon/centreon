import { atom } from 'jotai';

import {
  DeleteResourceAccessRuleType,
  DeleteType,
  DuplicateResourceAccessRuleType,
  ModalMode,
  ResourceAccessRuleType,
  ResourceTypeEnum
} from './models';

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

export const isDeleteDialogOpenAtom = atom<boolean>(false);
export const deleteResourceAccessRuleAtom = atom<DeleteResourceAccessRuleType>({
  deleteType: DeleteType.SingleItem,
  id: null
});

export const duplicatedRuleAtom = atom<DuplicateResourceAccessRuleType>({
  id: null
});
export const isDuplicateDialogOpenAtom = atom<boolean>(false);

export const selectedDatasetsAtom = atom<
  Array<{ ids: Array<number>; type: ResourceTypeEnum }>
>([{ ids: [], type: ResourceTypeEnum.Empty }]);

export const selectedDatasetFiltersAtom = atom<
  Array<
    Array<{
      allOfResourceType: boolean;
      ids: Array<number>;
      type: ResourceTypeEnum;
    }>
  >
>([[{ allOfResourceType: false, ids: [], type: ResourceTypeEnum.Empty }]]);

export const allContactsSelectedAtom = atom<boolean>(false);
export const allContactGroupsSelectedAtom = atom<boolean>(false);

export const isCloseModalConfirmationDialogOpenAtom = atom<boolean>(false);
export const isDirtyAtom = atom<boolean>(false);
