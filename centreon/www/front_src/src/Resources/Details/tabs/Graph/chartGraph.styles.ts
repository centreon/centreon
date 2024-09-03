import { makeStyles } from 'tss-react/mui';

export const useChartGraphStyles = makeStyles()((theme) => ({
  container: {
    overflow: 'visible',
    backgroundColor: theme.palette.background.paper
  },
  commentContainer: {
    padding: theme.spacing(1),
    backgroundColor: theme.palette.background.default,
    justifyContent: 'center',
    display: 'flex',
    flexDirection: 'column'
  }
}));
