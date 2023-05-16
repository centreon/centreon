import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  form: {
    padding: theme.spacing(0, 2, 2)
  },
  reducePanel: {
    display: 'flex',
    justifyContent: 'flex-end',
    padding: theme.spacing(1, 2, 0)
  }
}));

export default useStyles;
