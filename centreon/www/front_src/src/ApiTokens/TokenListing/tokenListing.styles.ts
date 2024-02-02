import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    margin: theme.spacing(1, 3, 0, 3)
  },
  divider: {
    marginTop: theme.spacing(2)
  },
  title: {
    color: theme.palette.primary.main
  }
}));
