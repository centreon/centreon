import { makeStyles } from 'tss-react/mui';

export const useLegendStyles = makeStyles<{ direction: 'row' | 'column' }>()(
  (theme, { direction }) => ({
    legend: {
      border: `1px solid ${theme.palette.divider}`,
      borderRadius: theme.shape.borderRadius,
      display: 'flex',
      flexDirection: direction,
      gap: theme.spacing(1),
      height: 'fit-content',
      padding: theme.spacing(1),
      width: 'fit-content'
    },
    legendItem: {
      borderRadius: theme.shape.borderRadius,
      cursor: 'pointer',
      height: theme.spacing(2),
      width: theme.spacing(2)
    },
    legendItems: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(0.75)
    },
    tooltip: {
      backgroundColor: theme.palette.background.paper,
      boxShadow: theme.shadows[3],
      color: theme.palette.text.primary,
      padding: 0,
      position: 'relative'
    }
  })
);
