import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  buttons: {
    alignItems: 'center',
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: theme.spacing(2)
  }
}));

export default useStyles;
