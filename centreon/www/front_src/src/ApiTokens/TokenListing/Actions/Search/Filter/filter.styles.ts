import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    minWidth: theme.spacing(45)
  },
  input: {
    margin: theme.spacing(2, 0),
    minWidth: theme.spacing(40)
  }
}));
