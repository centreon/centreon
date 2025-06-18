import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  comment: {
    display: 'block',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  container: {
    display: 'flex',
    gap: theme.spacing(0.5)
  }
}));

export default useStyles;
