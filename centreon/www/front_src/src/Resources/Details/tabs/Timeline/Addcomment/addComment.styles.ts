import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  footer: {
    display: 'flex',
    justifyContent: 'end',
    marginTop: theme.spacing(0.5)
  }
}));
