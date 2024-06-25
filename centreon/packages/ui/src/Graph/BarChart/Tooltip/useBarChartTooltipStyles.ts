import { makeStyles } from 'tss-react/mui';

export const useBarChartTooltipStyles = makeStyles()((theme) => ({
  metric: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1),
    weidth: '100%'
  },
  metricColorBox: {
    borderRadius: theme.shape.borderRadius,
    flexShrink: 0,
    height: theme.spacing(1.5),
    width: theme.spacing(1.5)
  },
  metricName: {
    flexGrow: 1
  },
  metrics: {
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(0.5),
    width: '100%'
  },
  tooltipContainer: {
    padding: theme.spacing(1)
  }
}));
