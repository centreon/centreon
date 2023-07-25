import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    borderRight: `1px dotted ${theme.palette.text.primary}`,
    display: 'flex',
    gap: theme.spacing(2),
    paddingRight: theme.spacing(1.5)
  },
  name: {
    fontWeight: theme.typography.fontWeightMedium
  },
  panelHeader: {
    background: theme.palette.background.paper,
    boxSizing: 'border-box',
    display: 'flex',
    justifyContent: 'space-between',
    padding: theme.spacing(1.5, 2),
    position: 'sticky',
    top: 0,
    zIndex: theme.zIndex.drawer + 1
  },
  rightHeader: {
    alignItems: 'center',
    display: 'flex'
  },
  title: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  }
}));

export default useStyles;
