import { makeStyles } from 'tss-react/mui';

export const useClockStyles = makeStyles()((theme) => ({
  clockInformation: {
    display: 'flex',
    flexDirection: 'row',
    maxWidth: theme.spacing(40),
    minWidth: theme.spacing(32)
  },
  container: {
    display: 'grid',
    gridTemplateRows: '0.4fr 1fr',
    height: '100%',
    width: '100%'
  },
  date: {
    alignSelf: 'right'
  },
  icon: {
    alignSelf: 'left'
  },
  timezone: {
    alignSelf: 'center'
  }
}));
