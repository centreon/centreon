import { makeStyles } from 'tss-react/mui';

export const useClockStyles = makeStyles()((theme) => ({
  background: {
    bottom: 0,
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0
  },
  clockInformation: {
    alignItems: 'center',
    display: 'grid',
    gridTemplateColumns: '1fr minmax(110px, 0.5fr) 1fr',
    width: '100%'
  },
  clockLabel: {
    alignContent: 'center',
    alignItems: 'flex-start',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  },
  container: {
    color: theme.palette.common.white,
    display: 'grid',
    gridTemplateColumns: '100%',
    gridTemplateRows: '30px 1fr',
    height: '100%',
    position: 'relative',
    width: '100%',
    zIndex: 1
  },
  date: {
    justifySelf: 'start'
  },
  icon: {
    justifySelf: 'end'
  },
  timezone: {
    justifySelf: 'center'
  }
}));
