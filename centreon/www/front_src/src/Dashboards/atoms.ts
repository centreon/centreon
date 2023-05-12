import { atom } from 'jotai';

import { SelectedDashboard } from './models';

export const selectedDashboardAtom = atom<SelectedDashboard | null>(null);
export const isDialogOpenAtom = atom((get) =>
  Boolean(get(selectedDashboardAtom))
);
export const openDialogAtom = atom(
  null,
  (_, set, selectedDashboard: SelectedDashboard) => {
    set(selectedDashboardAtom, selectedDashboard);
  }
);
export const closeDialogAtom = atom(null, (get, set) => {
  set(selectedDashboardAtom, null);
});

export const selectedDashboardVariantAtom = atom(
  (get) => get(selectedDashboardAtom)?.variant
);
