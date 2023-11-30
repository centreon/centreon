import { usePlaylistConfigStyles } from '../PlaylistConfig.styles';

import SelectDashboard from './SelectDashboard';

const DashboardsSelection = (): JSX.Element => {
  const { classes } = usePlaylistConfigStyles();

  return (
    <div className={classes.dashboards}>
      <SelectDashboard />
    </div>
  );
};

export default DashboardsSelection;
