import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1.5)
  },
  container: {
    alignItems: 'center',
    display: 'flex'
  },
  filters: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  },
  searchBar: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
    paddingInline: theme.spacing(1),
    width: '100%'
  }
}));
