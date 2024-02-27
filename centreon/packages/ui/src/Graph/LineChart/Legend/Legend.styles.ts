import { makeStyles } from 'tss-react/mui';

import { margin } from '../common';

interface MakeStylesProps {
  limitLegendRows?: boolean;
}

export const legendWidth = 21;
const legendItemHeight = 5.25;
const legendItemHeightCompact = 2;

export const useStyles = makeStyles<MakeStylesProps>()(
  (theme, { limitLegendRows }) => ({
    highlight: {
      color: theme.typography.body1.color
    },
    item: {
      minWidth: theme.spacing(legendWidth)
    },
    items: {
      '&[data-mode="compact"]': {
        gridAutoRows: theme.spacing(legendItemHeightCompact),
        height: limitLegendRows
          ? theme.spacing(legendItemHeightCompact * 2 + 1.5)
          : 'unset'
      },
      columnGap: theme.spacing(3),
      display: 'grid',
      gridAutoRows: theme.spacing(legendItemHeight),
      gridTemplateColumns: `repeat(auto-fit, ${theme.spacing(legendWidth)})`,
      maxHeight: limitLegendRows
        ? theme.spacing(legendItemHeight * 2 + 1)
        : 'unset',
      overflowY: 'auto',
      rowGap: theme.spacing(1),
      width: '100%'
    },
    legend: {
      marginLeft: margin.left,
      marginRight: margin.right,
      overflow: 'hidden'
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
      minWidth: theme.spacing(1.5),
      width: theme.spacing(1.5)
    },
    legendName: {
      '&[data-mode="compact"]': {
        maxWidth: theme.spacing(legendWidth * 0.5)
      },
      maxWidth: theme.spacing(legendWidth * 0.75)
    },
    markerAndLegendName: {
      alignItems: 'center',
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(0.5)
    },
    minMaxAvgContainer: {
      columnGap: theme.spacing(0.5),
      display: 'grid',
      gridTemplateColumns: 'repeat(2, min-content)',
      whiteSpace: 'nowrap'
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
