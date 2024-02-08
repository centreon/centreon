import { makeStyles } from 'tss-react/mui';

export const usePieStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(3)
  },
  legends: {
    marginTop: theme.spacing(3)
  },
  pieTitle: {
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    marginBottom: theme.spacing(2)
  },
  svgContainer: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.panelGroups,
    borderRadius: '100%',
    display: 'flex',
    justifyContent: 'center'
  }
}));
