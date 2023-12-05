import { atom } from 'jotai';
import { dec, equals, findIndex, inc, isNil, pluck } from 'ramda';

import { Dashboard } from '../../components/DashboardPlaylists/models';

export const isRotatingDashboardsAtom = atom(true);
export const displayedDashboardAtom = atom<number | null>(null);

interface ChangeDashboardProps {
  dashboards: Array<Dashboard>;
  direction: 'next' | 'previous';
}

export const changeDashboardDerivedAtom = atom(
  null,
  (get, set, { direction, dashboards }: ChangeDashboardProps) => {
    const displayedDashboard = get(displayedDashboardAtom);

    const dashboardIds = pluck('id', dashboards);
    const displayedDashboardIndex = findIndex(
      equals(displayedDashboard),
      dashboardIds
    );

    const maxDahboardsIndex = dec(dashboardIds.length);

    if (isNil(displayedDashboard)) {
      set(displayedDashboardAtom, dashboardIds[0]);

      return;
    }

    if (
      equals(direction, 'next') &&
      equals(displayedDashboardIndex, maxDahboardsIndex)
    ) {
      set(displayedDashboardAtom, dashboardIds[0]);

      return;
    }

    if (equals(direction, 'previous') && equals(displayedDashboardIndex, 0)) {
      set(displayedDashboardAtom, dashboardIds[maxDahboardsIndex]);

      return;
    }

    const changeFunction = equals(direction, 'previous') ? dec : inc;

    set(
      displayedDashboardAtom,
      dashboardIds[changeFunction(displayedDashboardIndex)]
    );
  }
);

interface SelectDashboardProps {
  dashboardId: number;
  dashboards: Array<Dashboard>;
}

export const selectDashboardDerivedAtom = atom(
  null,
  (_, set, { dashboardId, dashboards }: SelectDashboardProps) => {
    const dashboardIds = pluck('id', dashboards);
    const selectedDashboardIndex = findIndex(equals(dashboardId), dashboardIds);

    set(displayedDashboardAtom, dashboardIds[selectedDashboardIndex]);
  }
);
