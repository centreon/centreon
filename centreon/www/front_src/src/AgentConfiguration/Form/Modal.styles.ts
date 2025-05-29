import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  groups: {
    '&:hover': {
      background: theme.palette.background.listingHeader
    },
    background: theme.palette.background.listingHeader,
    color: theme.palette.common.white,
    flexDirection: 'row-reverse',
    justifyContent: 'space-between',
    paddingInline: theme.spacing(1.25)
  }
}));

export const useInputsStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontWeight: theme.typography.fontWeightMedium,
    fontSize: theme.typography.subtitle1.fontSize
  }
}));
