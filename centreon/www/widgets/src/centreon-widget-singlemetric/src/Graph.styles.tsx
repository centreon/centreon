import { makeStyles } from 'tss-react/mui';

export const useGraphStyles = makeStyles()({
  content: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'center'
  },
  graphContainer: { height: '100%', position: 'relative' }
});
