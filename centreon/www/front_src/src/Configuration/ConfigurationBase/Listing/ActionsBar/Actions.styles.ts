import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  bar: {
    display: 'flex'
  },
  actions: {
    display: 'flex',
    gap: theme.spacing(1.5)
  },
  searchBar: {
    width: '100%',
    paddingInline: theme.spacing(2),
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
  },
  moreActions: {
    [theme.breakpoints.down('md')]: {
      display: 'none'
    }
  },
  ActionsList: {
    width: theme.spacing(18)
  }
}));
