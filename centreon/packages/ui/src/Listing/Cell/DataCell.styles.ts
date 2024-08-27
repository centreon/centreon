import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  cell: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    overflow: 'hidden',
    whiteSpace: 'nowrap'
  },
  clickable: {
    cursor: 'default'
  },
  componentColumn: {
    width: theme.spacing(2.75)
  },
  rowNotHovered: {
    color: theme.palette.text.secondary
  },
  text: {
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

export { useStyles };
