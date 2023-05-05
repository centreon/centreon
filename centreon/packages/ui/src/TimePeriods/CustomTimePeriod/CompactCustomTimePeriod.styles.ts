import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  button: {
    height: '100%',
    minWidth: 120,
    padding: theme.spacing(0, 0.5)
  },
  buttonContent: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'min-content auto'
  },
  compactFromTo: {
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(0.5, 0, 0.5, 0)
  },
  date: {
    display: 'flex'
  },
  dateLabel: {
    display: 'flex',
    flex: 1,
    paddingRight: 4
  },
  error: {
    textAlign: 'center'
  },
  fromTo: {
    alignItems: 'center',
    columnGap: theme.spacing(0.5),
    display: 'grid',
    gridTemplateColumns: 'repeat(2, auto)'
  },
  minimalPickers: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'min-content auto'
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
    display: 'flex',
    flexDirection: 'row'
  }
}));

export default useStyles;
