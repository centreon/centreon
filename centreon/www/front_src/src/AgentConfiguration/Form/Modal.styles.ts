import { makeStyles } from 'tss-react/mui';

export const useFormStyles = makeStyles()((theme) => ({
  groups: {
    '&:hover': {
      background: theme.palette.background.listingHeader
    },
    background: theme.palette.background.listingHeader,
    borderRadius: theme.spacing(1),
    color:
      theme.palette.mode === 'dark'
        ? theme.palette.common.white
        : theme.palette.common.black,
    flexDirection: 'row-reverse',
    justifyContent: 'space-between',
    paddingInline: theme.spacing(1.25)
  }
}));

export const useInputsStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontSize: theme.typography.subtitle1.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));
