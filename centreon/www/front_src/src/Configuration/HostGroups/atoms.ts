import { atom } from 'jotai';
import { atomWithReset } from 'jotai/utils';

export const tooltipPageAtom = atomWithReset(1);

// form
export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
