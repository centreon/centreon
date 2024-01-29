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
    [theme.breakpoints.down('sm')]: {
      flexDirection: 'column',
      gap: theme.spacing(0.5)
    }
  },
  date: {
    minWidth: theme.spacing(12),
    textAlign: 'start'
  },
  error: {
    textAlign: 'center'
  },
  label: {
    minWidth: theme.spacing(3.5),
    textAlign: 'end'
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
    columnGap: theme.spacing(0.5),
    display: 'flex',
    flexDirection: 'row',
    [theme.breakpoints.down('sm')]: {
      alignItems: 'flex-start'
    }
  }
}));

export default useStyles;
