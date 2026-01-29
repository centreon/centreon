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
      {!isViewer && <AddDashboard openConfig={openConfig} />}
      <Box className={classes.filter}>
        <Filter />
      </Box>
      <ViewMode />
      <FavoriteFilter />
    </Box>
  );
};

export default Actions;
