import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  actionBar: {
    alignItems: 'center',
    display: 'flex'
  },
  dataTableScrollContainer: {
    height: '100%',
    overflowY: 'auto'
  },
  loadingIndicator: {
    height: 3,
    width: '100%'
  }
}));

export { useStyles };
