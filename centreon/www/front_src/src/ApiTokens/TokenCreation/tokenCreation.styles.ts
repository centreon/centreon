import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    justifyContent: 'end'
  },
  containerTitle: {
    width: theme.spacing(56)
  },
  input: {
    marginBottom: theme.spacing(2.5),
    width: theme.spacing(56)
  },
  invisible: {
    display: 'none'
  },
  title: {
    color: theme.palette.primary.main
  }
}));
