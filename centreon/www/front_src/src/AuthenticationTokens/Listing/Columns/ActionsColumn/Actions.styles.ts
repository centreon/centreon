import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between',
    width: theme.spacing(6.5)
  },
  copyIcon: {
    fontSize: theme.spacing(2.25)
  },
  removeButton: {
    '& :hover': {
      color: theme.palette.error.main
    },
    color: theme.palette.primary.main
  },
  removeIcon: {
    color: theme.palette.error.main,
    fontSize: theme.spacing(2.5)
  }
}));

export const useStatusStyles = makeStyles()((theme) => ({
  container: {
    marginLeft: theme.spacing(1)
  }
}));
