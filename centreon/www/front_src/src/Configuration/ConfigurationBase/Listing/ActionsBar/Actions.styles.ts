import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1.5),
    alignItems: 'center'
  },
  removeButton: {
    '& :hover': {
      color: theme.palette.error.main
    }
  },
  removeIcon: {
    fontSize: theme.spacing(3)
  },
  duplicateIcon: {
    fontSize: theme.spacing(2.25)
  },
  buttons: {
    [theme.breakpoints.down('lg')]: {
      display: 'none'
    }
  },
  iconButtons: {
    [theme.breakpoints.up('lg')]: {
      display: 'none'
    }
  },
  searchBar: {
    width: '100%',
    paddingInline: theme.spacing(2.5),
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',

    [theme.breakpoints.down('md')]: {
      display: 'none'
    }
  }
}));
