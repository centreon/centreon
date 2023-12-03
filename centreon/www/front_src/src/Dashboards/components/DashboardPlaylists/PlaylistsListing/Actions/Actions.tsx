import { Box } from '@mui/material';

import { useIsViewerUser } from '../hooks';

import Filter from './Filter';
import AddPlaylist from './Add';
import { useActionsStyles } from './useActionsStyles';

const Actions = ({ openConfig }: { openConfig: () => void }): JSX.Element => {
  const { classes } = useActionsStyles();

  const isViewer = useIsViewerUser();

  return (
    <Box className={classes.actions}>
      {!isViewer && <AddPlaylist openConfig={openConfig} />}
      <Filter />
    </Box>
  );
};

export default Actions;
