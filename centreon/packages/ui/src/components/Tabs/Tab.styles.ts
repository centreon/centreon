import { makeStyles } from 'tss-react/mui';

export const useTabsStyles = makeStyles()((theme) => ({
  indicator: {
    bottom: 'unset'
  },
  tab: {
    '&[aria-selected="true"]': {
      color: theme.palette.text.primary,
      fontWeight: theme.typography.fontWeightBold
    },
    color: theme.palette.text.primary,
    fontWeight: theme.typography.fontWeightRegular,
    marginRight: theme.spacing(2),
    minHeight: 0,
    minWidth: 0,
    padding: theme.spacing(0.5, 0)
  },
  tabPanel: {
    padding: theme.spacing(1, 0, 0)
  },
  tabs: {
    minHeight: theme.spacing(4.5)
  }
}));
