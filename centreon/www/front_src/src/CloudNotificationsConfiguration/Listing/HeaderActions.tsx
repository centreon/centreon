import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import ActionAdd from '../Actions/AddAction';
import ActionDelete from '../Actions/DeleteAction';
import ActionDuplicate from '../Actions/DuplicateAction';

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
      <ActionAdd />
      <Box className={classes.icons}>
        <ActionDelete />
        <ActionDuplicate />
      </Box>
    </Box>
  );
};

export default Actions;
