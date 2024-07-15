import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()(() => ({
  barStack: {
    height: '98%',
    width: '98%'
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
    width: '96%'
  },
  singleHorizontalBar: {
    height: '49%'
  },
  verticalBar: {
    width: '49%'
  }
}));
