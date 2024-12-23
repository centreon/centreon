import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()(() => ({
  barStack: {
    height: '98%',
    width: '100%'
  },
  container: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  pieChart: {
    height: '100%',
    width: '98%'
  },
  singleHorizontalBar: {
    height: '100%'
  },
  verticalBar: {
    width: '98%'
  }
}));
