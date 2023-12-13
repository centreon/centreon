import { useCallback, useEffect, useRef } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNil } from 'ramda';

import { Box } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { Dashboard } from '../../../../components/DashboardPlaylists/models';
import {
  displayedDashboardAtom,
  selectDashboardDerivedAtom
} from '../../atoms';

import { useDashboardsStyles } from './Footer.styles';
import { useScrollListener } from './useScrollListener';

interface Props {
  dashboards: Array<Dashboard>;
}

const Dashboards = ({ dashboards }: Props): JSX.Element => {
  const { classes } = useDashboardsStyles();

  const dashboardsRef = useRef<HTMLDivElement | undefined>();

  const displayedDashboard = useAtomValue(displayedDashboardAtom);
  const selectDashboard = useSetAtom(selectDashboardDerivedAtom);

  useScrollListener(dashboardsRef);

  const isSelected = useCallback(
    (id: number) => {
      return equals(displayedDashboard, id);
    },
    [displayedDashboard]
  );

  const changeDisplaysDashboard = (dashboardId: number) => (): void => {
    selectDashboard({ dashboardId, dashboards });
  };

  useEffect(() => {
    if (isNil(displayedDashboard)) {
      return;
    }
    dashboardsRef.current
      ?.querySelector(`[data-dashboardId="${displayedDashboard}"]`)
      ?.scrollIntoView({ behavior: 'smooth' });
  }, [dashboardsRef.current, displayedDashboard]);

  return (
    <Box className={classes.container}>
      <Box className={classes.dashboards} ref={dashboardsRef}>
        {dashboards.map(({ id, name }) => (
          <Box
            className={classes.dashboard}
            data-dashboardId={id}
            data-selected={isSelected(id)}
            key={`${id}_${name}`}
            role="button"
            onClick={changeDisplaysDashboard(id)}
          >
            <EllipsisTypography className={classes.text}>
              {name}
            </EllipsisTypography>
          </Box>
        ))}
      </Box>
    </Box>
  );
};

export default Dashboards;
