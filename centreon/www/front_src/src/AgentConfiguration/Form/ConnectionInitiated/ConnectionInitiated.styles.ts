import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  indicator: {
    bottom: 'unset'
  },
  tab: {
    '&[aria-selected="true"]': {
      color: theme.palette.text.primary,
      fontWeight: theme.typography.fontWeightBold,
      background: theme.palette.background.default
    },
    color: theme.palette.text.primary,
    fontWeight: theme.typography.fontWeightBold,
    minHeight: 45
  },
  tabPanel: {
    background: theme.palette.background.default
  },
  tabs: {
    width: '100%',
    height: '100%',
    borderRadius: theme.spacing(0.5)
  },
  tabContent: {
    display: 'flex',
    gap: theme.spacing(1),
    alignItems: 'center'
  },
  doneIcon: {
    fontSize: theme.spacing(2.25),
    background: theme.palette.chip.color.success,
    borderRadius: 50,
    color: 'white',
    padding: '2px'
  },
  InfoIcon: {
    fontSize: theme.spacing(2.5)
  }
}));

export const useAgentInitiatedStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(3)
  },
  inputs: {
    display: 'flex',
    gap: theme.spacing(3)
  },
  input: {
    backgroundColor: theme.palette.background.default
  }
}));
