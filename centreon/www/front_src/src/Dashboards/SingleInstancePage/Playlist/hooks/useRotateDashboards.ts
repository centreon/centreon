import { useEffect, useRef } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { gte } from 'ramda';

import {
  changeDashboardDerivedAtom,
  displayedDashboardAtom,
  isRotatingDashboardsAtom
} from '../atoms';
import { Dashboard } from '../../../components/DashboardPlaylists/models';

interface Props {
  dashboards: Array<Dashboard>;
  playlistId: number;
  rotationTime: number;
}

const useRotationDashboards = ({
  dashboards,
  playlistId,
  rotationTime
}: Props): void => {
  const rotationIntervalRef = useRef<NodeJS.Timeout | undefined>();
  const [isRotatingDashboards, setIsRotatingDashboards] = useAtom(
    isRotatingDashboardsAtom
  );
  const [displayedDashboard, setDisplayedDashboard] = useAtom(
    displayedDashboardAtom
  );
  const changeDashboard = useSetAtom(changeDashboardDerivedAtom);

  const goToNextDashboard = (): void => {
    changeDashboard({ dashboards, direction: 'next' });
  };

  const reset = (): void => {
    if (rotationIntervalRef) {
      clearInterval(rotationIntervalRef.current);
    }
    setIsRotatingDashboards(true);
    setDisplayedDashboard(null);
  };

  useEffect(() => {
    goToNextDashboard();

    return reset;
  }, []);

  useEffect(() => {
    if (gte(dashboards.length, 2) && isRotatingDashboards) {
      clearInterval(rotationIntervalRef.current);
      rotationIntervalRef.current = setInterval(
        goToNextDashboard,
        rotationTime * 1000
      );

      return;
    }

    clearInterval(rotationIntervalRef.current);
  }, [playlistId, isRotatingDashboards, displayedDashboard]);
};

export default useRotationDashboards;
