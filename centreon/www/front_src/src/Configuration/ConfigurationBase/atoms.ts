import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { Configuration } from '../models';
import { FormActions, FormState } from './models';

export const configurationAtom = atom<Configuration | null>({
  api: { endpoints: null },
  defaultSelectedColumnIds: [],
  filtersInitialValues: { name: '' },
  resourceType: null
});

export const formStateAtom = atom<FormState>({
  id: null,
  isOpen: false,
  mode: 'add'
});

export const formActionsAtom = atom<FormActions | null>(null);

// A width the user dragged a configuration form panel to, kept across
// sessions. The key names no module on purpose: every configuration page
// migrated to the panel shares the width the user settled on. `null` means
// untouched, so the screen decides.
export const panelWidthAtom = atomWithStorage<number | null>(
  'configuration_panel_width',
  null
);

export const isFormDirtyAtom = atom<boolean>(false);
export const isCloseConfirmationDialogOpenAtom = atom<boolean>(false);
export const isResetConfirmationDialogOpenAtom = atom<boolean>(false);
