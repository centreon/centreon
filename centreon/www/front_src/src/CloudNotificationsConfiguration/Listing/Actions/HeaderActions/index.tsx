import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import Add from './Add';
import Delete from './Delete';
import Duplicate from './Duplicate';

const useStyle = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(4)
  },
  icons: {
    display: 'flex',
    gap: theme.spacing(1.5)
  }
}));

const Actions = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.actions}>
      <Add />
      <Box className={classes.icons}>
        <Delete />
        <Duplicate />
      </Box>
    </Box>
  );
};

export default Actions;
