import { makeStyles } from 'tss-react/mui';

export const useInputStyles = makeStyles()((theme) => ({
  addDatasetButton: {
    marginTop: theme.spacing(4),
    width: '33%'
  },
  contactsAndContactGroups: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  },
  resourceSelection: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'flex-start',
    width: '100%'
  },
  ruleProperties: {
    backgroundColor: theme.palette.background.default,
    borderRadius: '4px',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'flex-start',
    padding: theme.spacing(2)
  },
  titleGroup: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));
