import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonsContainer: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: theme.spacing(40)
  },
  checkbox: {
    paddingLeft: theme.spacing(0.25)
  },
  checkboxContainer: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: '70%'
  },
  container: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(1),
    minWidth: theme.spacing(44),
    padding: theme.spacing(2, 0)
  },
  helperText: {
    textAlign: 'center'
  },
  input: {
    marginBottom: theme.spacing(1.5),
    width: theme.spacing(40)
  },
  labelStatus: {
    paddingLeft: theme.spacing(0.25),
    width: '20%'
  },
  statusContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    marginBottom: theme.spacing(1.5),
    width: theme.spacing(40)
  }
}));
