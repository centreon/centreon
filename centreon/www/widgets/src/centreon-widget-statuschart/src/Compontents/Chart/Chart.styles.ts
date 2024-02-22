import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()(() => ({
  container: {
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  legendWrapper: {
    alignItems: 'center',
    display: 'flex'
  },
  pieChart: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  }
}));
