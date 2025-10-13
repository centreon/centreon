import { makeStyles } from 'tss-react/mui';

export const useGraphStyles = makeStyles()((theme) => ({
  graphsCapMessage: {
    alignItems: 'center',
    color: theme.palette.grey[600],
    display: 'flex',
    flexGrow: 1,
    height: '90%',
    justifyContent: 'center'
  }
}));
