import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  modalHeader: {
    fontSize: theme.typography.h5.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    marginBottom: theme.spacing(-3)
  }
}));

export const useFormStyles = makeStyles()((theme) => ({
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
  }
}));

export const useInputsStyles = makeStyles()((theme) => ({
  titleGroup: {
    fontWeight: theme.typography.fontWeightMedium,
    fontSize: theme.typography.subtitle1.fontSize
  }
}));
