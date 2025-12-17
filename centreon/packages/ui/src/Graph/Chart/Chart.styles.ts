import { makeStyles } from 'tss-react/mui';

export const useChartStyles = makeStyles()({
  baseWrapper: {
    position: 'relative'
  },
  tooltipChildren: { height: '100%', width: '100%' },
  wrapperContainer: {
    height: '100%',
    width: '100%',
    overflow: 'hidden'
  }
});
