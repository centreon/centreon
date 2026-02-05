import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  doneIcon: {
    background: theme.palette.chip.color.success,
    borderRadius: 50,
    color: 'white',
    fontSize: theme.spacing(2.25),
    padding: '2px'
  },
  InfoIcon: {
    fontSize: theme.spacing(2.5)
  },
  indicator: {
    bottom: 'unset'
  },
  tab: {
    '&[aria-selected="true"]': {
      background: theme.palette.background.default,
      color: theme.palette.text.primary,
      fontWeight: theme.typography.fontWeightBold
    },
    color: theme.palette.text.primary,
    fontWeight: theme.typography.fontWeightBold,
    minHeight: 45
  },
  tabContent: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  },
  tabPanel: {
    background: theme.palette.background.default
  },
  tabs: {
    borderRadius: theme.spacing(0.5),
    height: '100%',
    width: '100%'
  }
}));

export const useAgentInitiatedStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(3)
  },
  input: {
    backgroundColor: theme.palette.background.default
  },
  inputs: {
    display: 'flex',
    gap: theme.spacing(3)
  }
}));
