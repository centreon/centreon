import { Box } from '@mui/material';

import Filter from './Filter';
import AddPlaylist from './Add';
import { useActionsStyles } from './useActionsStyles';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <Box className={classes.actions}>
      <AddPlaylist />
      <Filter />
    </Box>
  );
};

export default Actions;
