import { makeStyles } from 'tss-react/mui';

export const ussHeaderChartStyles = makeStyles()({
  header: {
    display: 'grid',
    gridTemplateColumns: 'auto 1fr auto',
    width: '100%'
  },
  title: {
    lineHeight: '1.2',
    whiteSpace: 'pre-wrap'
  }
});
