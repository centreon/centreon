import { makeStyles } from 'tss-react/mui';

export const useColumnStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  icon: {
    fontSize: theme.spacing(2)
  },
  removeIcon: {
    color: theme.palette.error.main,
    fontSize: theme.spacing(2.25)
  }
}));
