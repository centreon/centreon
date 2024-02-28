import { lt } from 'ramda';
import { makeStyles } from 'tss-react/mui';

export const usePieStyles = makeStyles<{ svgSize: number }>()(
  (theme, { svgSize }) => ({
    container: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(2),
      justifyContent: 'center'
    },
    pieChartTooltip: {
      backgroundColor: theme.palette.background.paper,
      color: theme.palette.text.primary,
      padding: 0,
      position: 'relative'
    },
    svgContainer: {
      alignItems: 'center',
      backgroundColor: theme.palette.background.panelGroups,
      borderRadius: '100%',
      display: 'flex',
      justifyContent: 'center'
    },
    svgWrapper: {
      alignItems: 'center',
      display: 'flex',
      flexDirection: 'column',
      gap: theme.spacing(1),
      justifyContent: 'center'
    },
    title: {
      fontSize: lt(svgSize, 150)
        ? theme.typography.body1.fontSize
        : theme.typography.h6.fontSize,
      fontWeight: theme.typography.fontWeightMedium
    }
  })
);
