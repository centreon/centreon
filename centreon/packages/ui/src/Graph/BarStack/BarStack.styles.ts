import { makeStyles } from 'tss-react/mui';

export const useBarStackStyles = makeStyles()((theme) => ({
  barStackTooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative'
  },
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(3),
    justifyContent: 'center',
    padding: theme.spacing(2)
  },
  svgContainer: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.panelGroups,
    borderRadius: theme.spacing(0.5),
    display: 'flex',
    justifyContent: 'center'
  },
  svgWrapper: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  title: {
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    margin: 0,
    padding: 0
  }
}));
