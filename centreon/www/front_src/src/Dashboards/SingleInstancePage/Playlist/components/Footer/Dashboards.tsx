import { useCallback } from 'react';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Box } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { Dashboard } from '../../../../components/DashboardPlaylists/models';
import { displayedDashboardAtom } from '../../atoms';

import { useDashboardsStyles } from './Footer.styles';

interface Props {
  dashboards: Array<Dashboard>;
}

const Dashboards = ({ dashboards }: Props): JSX.Element => {
  const { classes } = useDashboardsStyles();
  const displayedDashboard = useAtomValue(displayedDashboardAtom);

  const isSelected = useCallback(
    (id: number) => {
      return equals(displayedDashboard, id);
    },
    [displayedDashboard]
  );

  return (
    <Box className={classes.container}>
      <Box className={classes.dashboards}>
        {dashboards.map(({ id, name }) => (
          <Box
            className={classes.dashboard}
            data-selected={isSelected(id)}
            key={`${id}_${name}`}
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
