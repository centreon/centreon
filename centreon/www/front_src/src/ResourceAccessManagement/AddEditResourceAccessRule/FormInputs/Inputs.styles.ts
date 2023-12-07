import { makeStyles } from 'tss-react/mui';

export const useInputStyles = makeStyles()((theme) => ({
  resourceSelection: {
    display: 'flex',
    width: '50%'
  },
  ruleProperties: {
    backgroundColor: theme.palette.grey[300],
    borderRadius: '4px',
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(2),
    width: '45%'
  },
  titleGroup: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));
