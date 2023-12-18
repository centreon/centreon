import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    justifyContent: 'space-between'
  },
  cancelAndSave: {
    display: 'flex',
    flex: 'row',
    gap: theme.spacing(2)
  }
}));
