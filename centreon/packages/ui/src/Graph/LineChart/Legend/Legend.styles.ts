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
      width: '100%'
    },
    items: {
      '&[data-as-list="true"]': {
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        width: 'fit-content'
      },
      '&[data-mode="compact"]': {
        gridAutoRows: theme.spacing(legendItemHeightCompact),
        height: limitLegendRows
          ? theme.spacing(legendItemHeightCompact * 2 + 1.5)
          : 'unset'
      },
      columnGap: theme.spacing(3),
      display: 'grid',
      gridAutoRows: theme.spacing(legendItemHeight),
      gridTemplateColumns: `repeat(auto-fit, minmax(${theme.spacing(legendWidth)}, 1fr))`,
      maxHeight: limitLegendRows
        ? theme.spacing(legendItemHeight * 2 + 1)
        : 'unset',
      overflowX: 'hidden',
      overflowY: 'auto',
      rowGap: theme.spacing(1),
      width: '100%'
    },
    legend: {
      '&[data-display-side="false"]': {
        marginLeft: margin.left,
        marginRight: margin.right,
        overflow: 'hidden'
      },
      '&[data-display-side="true"]': {
        height: '100%',
        marginTop: `${margin.top / 2}px`,
        overflowY: 'auto'
      }
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
      width: '100%'
    },
    containerList: {
      width: 'fit-content'
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
      maxWidth: '95%'
    },
    legendNameSide: {
      maxWidth: '95%'
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
      fontSize: '0.75rem',
      fontWeight: theme.typography.fontWeightMedium,
      lineHeight: 1,
      maxWidth: '250px'
    },
    textList: {
      fontSize: '0.75rem',
      fontWeight: theme.typography.fontWeightMedium
    },
    textListBottom: {
      width: 'auto'
    },
    textListSide: {
      width: '95%'
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
