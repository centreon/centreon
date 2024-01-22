import { makeStyles } from 'tss-react/mui';

export const useMetricsStyles = makeStyles()((theme) => ({
  radioCheckbox: {
    padding: theme.spacing(0.5)
  },
  resourceOption: {
    borderBottom: `1px solid ${theme.palette.divider}`,
    padding: theme.spacing(1, 0, 1, 6)
  },
  resourcesOptionContainer: {
    backgroundColor: theme.palette.background.paper,
    width: '100%'
  },
  resourcesOptionRadioCheckbox: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
