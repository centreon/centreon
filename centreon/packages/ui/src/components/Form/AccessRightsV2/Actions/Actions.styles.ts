import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    backgroundColor: theme.palette.background.paper,
    display: 'flex',
    justifyContent: 'space-between'
  },
  cancelAndSave: {
    display: 'flex',
    flex: 'row',
    gap: theme.spacing(2)
  }
}));
