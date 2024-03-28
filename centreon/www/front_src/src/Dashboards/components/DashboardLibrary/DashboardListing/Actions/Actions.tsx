import { Box } from '@mui/material';

import useIsViewerUser from '../useIsViewerUser';

import Filter from './Filter';
import AddDashboard from './AddDashboard';
import { useActionsStyles } from './useActionsStyles';
import ViewMode from './ViewMode';

const Actions = ({ openConfig }: { openConfig: () => void }): JSX.Element => {
  const { classes } = useActionsStyles();

  const isViewer = useIsViewerUser();

  return (
    <Box className={classes.actions}>
      {!isViewer && <AddDashboard openConfig={openConfig} />}
      <Filter />
      <ViewMode />
    </Box>
  );
};

export default Actions;
