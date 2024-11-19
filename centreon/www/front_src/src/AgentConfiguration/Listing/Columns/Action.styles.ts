import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
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
