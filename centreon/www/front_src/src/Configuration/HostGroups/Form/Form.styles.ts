import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  form: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%',
    height: '100%'
  },
  groups: {
    '&:hover': {
      background: theme.palette.background.listingHeader
    },
    background: theme.palette.background.listingHeader,
    color: theme.palette.common.white,
    flexDirection: 'row-reverse',
    height: theme.spacing(4.5),
    justifyContent: 'space-between',
    paddingInline: theme.spacing(1.25),
    margin: theme.spacing(3, 0, 1)
  },
  titleGroup: {
    fontWeight: theme.typography.fontWeightMedium,
    fontSize: theme.typography.subtitle1.fontSize
  }
}));
