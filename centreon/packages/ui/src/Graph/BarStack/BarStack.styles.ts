import { makeStyles } from 'tss-react/mui';

export const useBarStackStyles = makeStyles()((theme) => ({
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
    gap: theme.spacing(2)
  },
  title: {
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightBold
  }
}));
