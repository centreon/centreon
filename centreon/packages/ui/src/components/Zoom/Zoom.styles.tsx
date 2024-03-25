import { makeStyles } from 'tss-react/mui';

export const useZoomStyles = makeStyles()((theme) => ({
  actions: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    left: 0,
    position: 'absolute',
    transition: 'top 0.15s ease-out'
  },
  minimap: {
    '&:hover': {
      transform: 'scale(0.25)'
    },
    transform: 'scale(0.15)',
    transition: 'transform 0.15s ease-out'
  },
  minimapBackground: {
    fill: theme.palette.background.default,
    stroke: theme.palette.background.default
  },
  minimapZoom: {
    fill: theme.palette.text.primary
  },
  svg: {
    '&[data-is-grabbing="true"]': {
      cursor: 'grabbing'
    },
    cursor: 'grab',
    touchAction: 'none'
  }
}));
