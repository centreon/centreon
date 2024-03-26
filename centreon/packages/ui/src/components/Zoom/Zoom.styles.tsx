import { makeStyles } from 'tss-react/mui';

import { minimapScale } from './constants';

export const useZoomStyles = makeStyles()((theme) => ({
  actions: {
    bottom: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    left: theme.spacing(1),
    position: 'absolute',
    transition: 'top 0.15s ease-out'
  },
  minimap: {
    transform: `scale(${minimapScale})`
  },
  minimapBackground: {
    fill: theme.palette.background.default,
    stroke: theme.palette.background.default
  },
  minimapContainer: { left: 0, position: 'absolute', top: 0 },
  minimapZoom: {
    fill: theme.palette.text.primary
  },
  movingZone: {
    transition: 'transform 0.1s linear'
  },
  svg: {
    '&[data-is-grabbing="true"]': {
      cursor: 'grabbing'
    },
    cursor: 'grab',
    touchAction: 'none'
  }
}));
