import { atom } from 'jotai';

import { Dashboard, SelectedDashboard } from './models';

export const pageAtom = atom(1);
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

export const deleteDialogStateAtom = atom<{
  item: Dashboard | null;
  open: boolean;
}>({
  item: null,
  open: false
});

export const selectedDashboardShareAtom = atom<number | undefined>(undefined);
