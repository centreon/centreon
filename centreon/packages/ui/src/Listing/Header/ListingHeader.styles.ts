import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  headerLabelDragging: {
    cursor: 'grabbing'
  },
  root: {
    height: '100%',
    padding: theme.spacing(0, 1)
  },
  row: {
    display: 'contents'
  }
}));

export { useStyles };
