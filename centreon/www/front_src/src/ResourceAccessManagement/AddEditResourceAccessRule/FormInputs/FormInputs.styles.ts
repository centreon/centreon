import { makeStyles } from 'tss-react/mui';

export const useFormInputStyles = makeStyles()((theme) => ({
  contactsAndContactGroups: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  },
  contactsAndContactGroupsDivider: {
    marginBottom: theme.spacing(3),
    marginTop: theme.spacing(3)
  },
  resourceSelection: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'flex-start',
    width: '100%'
  },
  ruleProperties: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.spacing(0.5),
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
