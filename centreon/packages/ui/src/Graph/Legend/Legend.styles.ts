import { makeStyles } from 'tss-react/mui';

import { margin } from '../common';

interface MakeStylesProps {
  limitLegendRows?: boolean;
  panelWidth?: number;
}

export const useStyles = makeStyles<MakeStylesProps>()(
  (theme, { panelWidth, limitLegendRows }) => ({
    caption: {
      lineHeight: 1.2,
      marginRight: theme.spacing(0.5),
      maxWidth: panelWidth && 0.85 * panelWidth,
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      whiteSpace: 'nowrap'
    },
    highlight: {
      color: theme.typography.body1.color
    },
    item: {
      display: 'grid',
      gridTemplateColumns: 'min-content minmax(50px, 1fr)',
      marginBottom: theme.spacing(1)
    },
    items: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
      justifyContent: 'center',
      marginLeft: theme.spacing(0.5),
      maxHeight: limitLegendRows ? theme.spacing(19) : 'unset',
      overflowY: 'auto',
      width: '100%'
    },
    legend: {
      marginLeft: margin.left,
      maxHeight: theme.spacing(24),
      overflowX: 'hidden',
      overflowY: 'auto',
      width: '100%'
    },
    legendData: {
      display: 'flex',
      flexDirection: 'column'
    },
    legendName: {
      display: 'flex',
      flexDirection: 'row',
      justifyContent: 'start',
      overflow: 'hidden',
      textOverflow: 'ellipsis'
    },
    legendUnit: {
      justifyContent: 'start',
      marginLeft: 'auto',
      marginRight: theme.spacing(0.5),
      overflow: 'hidden',
      textOverflow: 'ellipsis'
    },
    legendValue: {
      fontWeight: theme.typography.body1.fontWeight
    },
    minMaxAvgContainer: {
      columnGap: theme.spacing(0.5),
      display: 'grid',
      gridAutoRows: theme.spacing(2),
      gridTemplateColumns: 'repeat(2, min-content)',
      whiteSpace: 'nowrap'
    },
    minMaxAvgValue: { fontWeight: 600 },
    normal: {
      color: theme.palette.text.primary
    },
    toggable: {
      cursor: 'pointer'
    }
  })
);
