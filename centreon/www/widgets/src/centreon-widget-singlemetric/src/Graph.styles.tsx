import { makeStyles } from 'tss-react/mui';

export const useGraphStyles = makeStyles()((theme) => ({
  content: { height: '100%', paddingTop: theme.spacing(2) },
  graphContainer: { height: '100%', position: 'relative' },
  title: {
    display: 'flex',
    justifyContent: 'center',
    position: 'absolute',
    width: '100%'
  }
}));
