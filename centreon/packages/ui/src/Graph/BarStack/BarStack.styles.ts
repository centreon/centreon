import { makeStyles } from 'tss-react/mui';
import { legendMaxHeight, legendMaxWidth } from './constants';

export const useStyles = makeStyles()({
  container: {
    display: 'grid',
    '&[data-has-title="false"]': {
      gridTemplateRows: 'auto'
    },
    '&[data-title-variant="xs"]': {
      gridTemplateRows: '40px auto'
    },
    '&[data-title-variant="sm"]': {
      gridTemplateRows: '20px auto'
    },
    '&[data-title-variant="md"]': {
      gridTemplateRows: '36px auto',
      textOverflow: 'clip',
      overflow: 'hidden'
    },
    height: '100%'
  },
  clippedTitle: {
    textOverflow: 'clip',
    overflow: 'hidden'
  }
});

export const useGraphAndLegendStyles = makeStyles()((theme) => ({
  graphAndLegend: {
    height: '100%',
    display: 'grid',
    '&[data-is-vertical="true"][data-display-legend="false"]': {
      gridTemplateColumns: '1fr'
    },
    '&[data-is-vertical="true"][data-display-legend="true"]': {
      gridTemplateColumns: `1fr ${legendMaxWidth}px`,
      gap: theme.spacing(0.5)
    },
    '&[data-display-legend="false"][data-is-vertical="false"]': {
      gridTemplateRows: '1fr'
    },
    '&[data-display-legend="true"][data-is-vertical="false"]': {
      gridTemplateRows: `1fr ${legendMaxHeight}px`,
      gap: theme.spacing(0.5)
    }
  },
  legend: {
    '&[data-is-vertical="false"]': {
      overflowY: 'auto'
    },
    '&[data-is-vertical="true"]': {
      alignSelf: 'center'
    }
  }
}));

export const useGraphStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    boxShadow: theme.shadows[3]
  }
}));
