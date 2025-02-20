import { makeStyles } from 'tss-react/mui';

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
