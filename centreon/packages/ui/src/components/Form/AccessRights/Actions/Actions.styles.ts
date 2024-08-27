import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  cancelAndSave: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    justifyContent: 'flex-end'
  }
}));
