import { makeStyles } from 'tss-react/mui';

export const useChartStyles = makeStyles()((theme) => ({
  baseWrapper: {
    position: 'relative'
  },
  tooManyGraphsMessage: {
    color: theme.palette.grey[600],
    left: '50%',
    position: 'absolute',
    top: '50%',
    transform: 'translate(-50%, -50%)',
    zIndex: 2,
  },
  tooltipChildren: { height: '100%', width: '100%' },
  wrapperContainer: {
    height: '100%',
    width: '100%',
    overflow: 'hidden'
  }
}));
