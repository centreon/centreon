import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  removeButton: {
    '& :hover': {
      color: theme.palette.error.main
    },
    color: theme.palette.primary.main
  },
  removeIcon: {
    color: theme.palette.error.main,
    fontSize: theme.spacing(2.5)
  },
  commandIcon: {
    fontSize: theme.spacing(2.5)
  }
}));
