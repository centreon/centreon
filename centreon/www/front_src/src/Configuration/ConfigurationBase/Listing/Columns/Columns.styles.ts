import { makeStyles } from 'tss-react/mui';

export const useColumnStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  duplicateIcon: {
    fontSize: theme.spacing(2.25)
  },
  icon: {
    fontSize: theme.spacing(2)
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
