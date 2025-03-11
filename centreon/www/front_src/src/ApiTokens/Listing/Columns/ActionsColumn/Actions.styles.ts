import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    gap: theme.spacing(1)
  },
  removeButton: {
    color: theme.palette.primary.main,
    '& :hover': {
      color: theme.palette.error.main
    }
  },
  removeIcon: {
    fontSize: theme.spacing(2.5),
    color: theme.palette.error.main
  },
  copyIcon: {
    fontSize: theme.spacing(2.25)
  }
}));
