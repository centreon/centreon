import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import Delete from './Delete';
import Duplicate from './Duplicate';

const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(3),
    justifyContent: 'space-between'
  }
}));

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Box className={classes.actions}>
      <Delete row={row} />
      <Duplicate row={row} />
    </Box>
  );
};

export default Actions;
