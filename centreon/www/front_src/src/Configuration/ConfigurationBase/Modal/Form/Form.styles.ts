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
