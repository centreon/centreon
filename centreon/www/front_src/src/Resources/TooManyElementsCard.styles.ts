import { makeStyles } from 'tss-react/mui';

export const useTooManyElementsCardStyles = makeStyles()((theme) => ({
  container: {
    overflow: 'visible',
    backgroundColor: theme.palette.background.paper
  },
  graphsCapMessage: {
    alignItems: 'center',
    color: theme.palette.grey[600],
    display: 'flex',
    flexGrow: 1,
    height: '100%',
    justifyContent: 'center'
  },
  graphsCapMessageLisitng: {
    alignItems: 'center',
    color: theme.palette.grey[600],
    display: 'flex',
    flexGrow: 1,
    height: '90%',
    justifyContent: 'center'
  }
}));
