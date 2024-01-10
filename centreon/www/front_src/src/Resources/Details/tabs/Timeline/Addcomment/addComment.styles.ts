import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  comment: {
    width: '100%'
  },
  container: {
    marginTop: theme.spacing(1.5)
  },
  footer: {
    display: 'flex',
    justifyContent: 'end',
    marginTop: theme.spacing(0.5)
  }
}));
