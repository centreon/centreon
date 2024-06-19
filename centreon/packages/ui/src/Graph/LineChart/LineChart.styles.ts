import { makeStyles } from 'tss-react/mui';

export const useLineChartStyles = makeStyles()({
  header: {
    display: 'grid',
    gridTemplateColumns: '0.4fr 1fr 0.4fr',
    width: '100%'
  },
  tooltipChildren: { height: '100%', width: '100%' }
});
