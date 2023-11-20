import { makeStyles } from 'tss-react/mui';

import { margin } from '../common';

interface MakeStylesProps {
  limitLegendRows?: boolean;
}

const legendWidth = 21;
const legendItemHeight = 5.25;

export const useStyles = makeStyles<MakeStylesProps>()(
  (theme, { limitLegendRows }) => ({
    highlight: {
      color: theme.typography.body1.color
    },
    item: {
      minWidth: theme.spacing(legendWidth)
    },
    items: {
      display: 'grid',
      gap: theme.spacing(1),
      gridAutoRows: theme.spacing(legendItemHeight),
      gridTemplateColumns: `repeat(auto-fit, ${theme.spacing(legendWidth)})`,
      maxHeight: limitLegendRows
        ? theme.spacing(legendItemHeight * 2 + 1)
        : 'unset',
      overflowY: 'auto',
      width: '100%'
    },
    legend: {
      marginLeft: margin.left,
      marginRight: margin.right,
      overflow: 'hidden'
    },
    legendData: {
      display: 'flex',
      flexDirection: 'column'
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
      gridTemplateColumns: 'repeat(2, min-content)',
      whiteSpace: 'nowrap'
    },
    normal: {
      color: theme.palette.text.primary
    },
    toggable: {
      cursor: 'pointer'
    }
  })
);

interface StylesProps {
  color?: string;
}

export const useLegendHeaderStyles = makeStyles<StylesProps>()(
  (theme, { color }) => ({
    container: {
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(0.5),
      width: '100%'
    },
    disabled: {
      color: theme.palette.text.disabled
    },
    icon: {
      backgroundColor: color,
      borderRadius: theme.shape.borderRadius,
      height: theme.spacing(1.5),
      width: theme.spacing(1.5)
    },
    legendName: {
      width: theme.spacing(legendWidth * 0.75)
    },
    markerAndLegendName: {
      alignItems: 'center',
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(0.5)
    },
    text: {
      fontWeight: theme.typography.fontWeightMedium,
      lineHeight: 1
    }
  })
);

export const useLegendContentStyles = makeStyles()((theme) => ({
  minMaxAvgValue: { fontWeight: theme.typography.fontWeightMedium },
  text: {
    lineHeight: 0.9
  }
}));

export const useLegendValueStyles = makeStyles()({
  text: {
    lineHeight: 1.4
  }
});
