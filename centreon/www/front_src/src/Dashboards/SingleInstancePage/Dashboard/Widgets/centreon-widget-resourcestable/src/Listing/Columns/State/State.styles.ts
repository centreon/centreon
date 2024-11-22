import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()({
  comment: {
    display: 'block',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  container: {
    display: 'flex',
    flexDirection: 'row',
    gridGap: 2,
    marginLeft: 2
  }
});

export default useStyles;
