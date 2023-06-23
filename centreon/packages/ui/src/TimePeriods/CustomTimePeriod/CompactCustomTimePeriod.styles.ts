import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {
    height: '100%',
    padding: theme.spacing(0, 0.5)
  },
  buttonContent: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex'
  },
  containerDates: {
    display: 'flex',
    gap: theme.spacing(0.5),
    [theme.breakpoints.down('sm')]: {
      columnGap: theme.spacing(0.5),
      flexDirection: 'column'
    }
  },
  date: {
    minWidth: theme.spacing(12.5),
    textAlign: 'start'
  },
  error: {
    textAlign: 'center'
  },
  label: {
    minWidth: theme.spacing(3),
    textAlign: 'start'
  },

  picker: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyItems: 'center',
    padding: theme.spacing(1, 2)
  },
  timeContainer: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row',
    [theme.breakpoints.down('sm')]: {
      alignItems: 'flex-start'
    }
  }
}));

export default useStyles;
