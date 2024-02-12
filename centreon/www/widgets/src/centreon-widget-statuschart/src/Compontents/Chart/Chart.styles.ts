import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles<{
  displaySingleChart: boolean;
}>()((theme, { displaySingleChart }) => ({
  barStack: { height: theme.spacing(8), width: '70%' },
  container: {
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  pieChart: { width: displaySingleChart ? '20%' : '40%' }
}));
