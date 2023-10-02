import { makeStyles } from 'tss-react/mui';

export const useGraphStyles = makeStyles()((theme) => ({
  content: {
    height: '100%',
    paddingTop: theme.spacing(5)
  },
  graphContainer: { height: '100%', position: 'relative' }
}));
