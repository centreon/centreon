import { Box } from '@mui/material';

import useIsViewerUser from '../useIsViewerUser';
import AddDashboard from './AddDashboard';
import Filter from './Filter';
import FavoriteFilter from './favoriteFilter';
import { useActionsStyles } from './useActionsStyles';
import ViewMode from './ViewMode';

const Actions = ({ openConfig }: { openConfig: () => void }): JSX.Element => {
  const { classes } = useActionsStyles();

  const isViewer = useIsViewerUser();

  return (
    <Box className={classes.actions}>
      <Box className={classes.leftCluster}>
        {!isViewer && <AddDashboard openConfig={openConfig} />}
        <ViewMode />
        <FavoriteFilter />
      </Box>
      <Box className={classes.filter}>
        <Filter />
      </Box>
      <Box className={classes.spacer} />
    </Box>
  );
};

export default Actions;
