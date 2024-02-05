import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  emptyDataCell: {
    flexDirection: 'column',
    gridColumn: 'auto / -1',
    justifyContent: 'center'
  },
  emptyDataRow: {
    display: 'contents'
  }
}));

export { useStyles };
