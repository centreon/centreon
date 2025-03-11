import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  input: {
    marginBottom: theme.spacing(2.5),
    width: theme.spacing(56)
  },
  containerTitle: {
    width: theme.spacing(56)
  },
  title: {
    color: theme.palette.primary.main
  }
}));
