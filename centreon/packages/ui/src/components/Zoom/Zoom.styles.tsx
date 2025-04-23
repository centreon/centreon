import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

import { minimapScale } from './constants';

export const useZoomStyles = makeStyles()((theme) => ({
  actions: {
    backgroundColor: alpha(theme.palette.background.paper, 0.8),
    display: 'flex',
    flexDirection: 'row'
  },
  actionsAndZoom: {
    '&[data-position="bottom-left"]': {
      alignItems: 'flex-start',
      bottom: 0,
      flexDirection: 'column-reverse',
      left: 0
    },
    '&[data-position="bottom-right"]': {
      alignItems: 'flex-end',
      bottom: 0,
      flexDirection: 'column-reverse',
      right: 0
    },
    '&[data-position="top-left"]': {
      alignItems: 'flex-start',
      flexDirection: 'column',
      left: 0,
      top: 0
    },
    '&[data-position="top-right"]': {
      alignItems: 'flex-end',
      flexDirection: 'column',
      right: 0,
      top: 0
    },
    display: 'flex',
    position: 'absolute',
    width: 'fit-content'
  },
  minimap: {
    transform: `scale(${minimapScale})`
  },
  minimapBackground: {
    fill: theme.palette.background.paper,
    stroke: theme.palette.background.paper
  },
  minimapContainer: {
    border: `1px solid ${theme.palette.divider}`,
    borderRadius: theme.shape.borderRadius
  },
  minimapZoom: {
    fill: theme.palette.primary.main,
    stroke: theme.palette.primary.main,
    strokeWidth: 10
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
