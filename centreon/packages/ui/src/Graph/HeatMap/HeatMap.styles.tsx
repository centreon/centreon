import { makeStyles } from 'tss-react/mui';

export const useHeatMapStyles = makeStyles()((theme) => ({
  arrow: {
    transform: 'transled3d(0px, 8px, 0px)'
  },
  heatMapTile: {
    alignItems: 'center',
    aspectRatio: '1 / 1',
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    justifyContent: 'center',
    width: '100%'
  },
  heatMapTileContent: {
    height: '100%',
    position: 'relative',
    width: '100%'
  },
  heatMapTooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    marginLeft: `${theme.spacing(1.25)} !important`,
    padding: 0,
    position: 'relative'
  },
  heatMapTooltipArrow: {
    transform: `translate3d(0px, ${theme.spacing(1)}, 0px) !important`
  }
}));
