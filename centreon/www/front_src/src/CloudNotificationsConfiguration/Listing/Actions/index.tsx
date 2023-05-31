import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import ActionDelete from './DeleteAction';
import ActionDuplicate from './DuplicateAction';

const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1.5)
  }
}));

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Box className={classes.actions}>
      <ActionDelete row={row} />
      <ActionDuplicate row={row} />
    </Box>
  );
};

export default Actions;
