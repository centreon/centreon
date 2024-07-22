import { useAtom } from 'jotai';

import { Grid } from '@mui/material';

import { dialogStateAtom } from '../../atoms';

import AddDashboard from './AddConnector';
import { useActionsStyles } from './useActionsStyles';
import Filters from './Filters/Filters';

const ActionsBar = (): JSX.Element => {
  const { classes } = useActionsStyles();

  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const openCreateDialog = (): void =>
    setDialogState({ ...dialogState, isOpen: true, variant: 'create' });

  return (
    <Grid container className={classes.actions}>
      <Grid item flex={2}>
        <AddDashboard openCreateDialog={openCreateDialog} />
      </Grid>
      <Grid item flex={5}>
        <Filters />
      </Grid>
    </Grid>
  );
};

export default ActionsBar;
