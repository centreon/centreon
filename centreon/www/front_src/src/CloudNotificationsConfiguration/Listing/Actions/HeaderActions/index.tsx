import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import Add from './Add';
import Delete from './Delete';

const useStyle = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(4)
  }
}));

const Actions = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.actions}>
      <Add />
      <Delete />
    </Box>
  );
};

export default Actions;
