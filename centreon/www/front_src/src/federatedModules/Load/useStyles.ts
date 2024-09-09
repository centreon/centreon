import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  moduleContainer: {
    alignItems: 'center',
    border: 'none',
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(0, 1)
  }
}));

export default useStyles;
