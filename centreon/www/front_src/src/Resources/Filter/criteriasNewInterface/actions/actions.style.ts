import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column'
  },
  popperButtonGroup: {
    zIndex: theme.zIndex.tooltip
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'row'
  }
}));
