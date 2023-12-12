import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(3)
  },
  filter: {
    width: theme.spacing(50)
  }
}));
