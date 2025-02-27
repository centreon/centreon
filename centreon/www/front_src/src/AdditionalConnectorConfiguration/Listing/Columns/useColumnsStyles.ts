import { makeStyles } from 'tss-react/mui';

export const useColumnStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  icon: {
    fontSize: theme.spacing(2)
  },
  removeButton: {
    color: theme.palette.primary.main,
    '& :hover': {
      color: theme.palette.error.main
    }
  },
  removeIcon: {
    fontSize: theme.spacing(2.25)
  }
}));
