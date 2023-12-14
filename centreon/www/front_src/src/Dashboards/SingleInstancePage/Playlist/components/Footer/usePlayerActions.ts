import { useAtom, useSetAtom } from 'jotai';

import {
  changeDashboardDerivedAtom,
  isRotatingDashboardsAtom
} from '../../atoms';
import { Dashboard } from '../../../../components/DashboardPlaylists/models';

interface Props {
  dashboards: Array<Dashboard>;
}

interface UsePlayerActionsState {
  isRotatingDashboards: boolean;
  next: () => void;
  playPause: () => void;
  previous: () => void;
}

export const usePlayerActions = ({
  dashboards
}: Props): UsePlayerActionsState => {
  const [isRotatingDashboards, setIsRotatingDashboards] = useAtom(
    isRotatingDashboardsAtom
  );
  const changeDashboard = useSetAtom(changeDashboardDerivedAtom);

  const next = (): void => {
    changeDashboard({
      dashboards,
      direction: 'next'
    });
  };

  const previous = (): void => {
    changeDashboard({
      dashboards,
      direction: 'previous'
    });
  };

  const playPause = (): void => {
    setIsRotatingDashboards(
      (currentIsRotationDashboards) => !currentIsRotationDashboards
    );
  };

  return {
    isRotatingDashboards,
    next,
    playPause,
    previous
  };
};
