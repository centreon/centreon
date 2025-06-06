import { makeStyles } from 'tss-react/mui';

export const ussHeaderChartStyles = makeStyles()({
  header: {
    display: 'grid',
    gridTemplateColumns: 'auto 1fr auto',
    width: '100%'
  },
  title: {
    whiteSpace: 'pre-wrap',
    lineHeight: '1.2'
  }
});
