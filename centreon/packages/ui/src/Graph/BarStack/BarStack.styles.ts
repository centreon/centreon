import { makeStyles } from 'tss-react/mui';

export const useBarStackStyles = makeStyles()((theme) => ({
  barStackTooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative'
  },
  container: {
    flexDirection: 'column',
    display: 'flex',
    gap: theme.spacing(1.5)
  },
  graphAndLegend: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr'
  },
  smallTitle: {
    fontSize: theme.typography.body1.fontSize
  },
  svgContainer: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.panelGroups,
    borderRadius: theme.spacing(1.25),
    display: 'flex',
    justifyContent: 'center',
    padding: theme.spacing(1)
  },
  svgWrapper: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyContent: 'center'
  },
  title: {
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    margin: 0,
    padding: 0,
    textAlign: 'center'
  }
}));
