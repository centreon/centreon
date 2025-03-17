import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    alignItems: 'center'
  },
  actions: {
    display: 'flex',
    alignItems: 'center',
    gap: theme.spacing(1.5)
  },
  searchBar: {
    width: '100%',
    paddingInline: theme.spacing(1),
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
  },
  filters: {
    maxWidth: theme.spacing(60),
    minWidth: theme.spacing(20),
    width: '100%'
  }
}));
