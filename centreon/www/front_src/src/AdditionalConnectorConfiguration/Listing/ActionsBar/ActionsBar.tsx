import { Grid } from '@mui/material';

import AddDashboard from './AddConnector';
import { useActionsStyles } from './useActionsStyles';
import Filters from './Filters/Filters';

const ActionsBar = ({
  openConfig
}: {
  openConfig: () => void;
}): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <Grid container className={classes.actions}>
      <Grid item flex={2}>
        <AddDashboard openConfig={openConfig} />
      </Grid>
      <Grid item flex={5}>
        <Filters />
      </Grid>
    </Grid>
  );
};

export default ActionsBar;
