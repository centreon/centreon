import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  form: {
    padding: theme.spacing(0, 4, 2, 2)
  },
  groups: {
    '&:hover': {
      background: theme.palette.background.listingHeader
    },
    background: theme.palette.background.listingHeader,
    color: theme.palette.common.white,
    flexDirection: 'row-reverse',
    height: theme.spacing(3.75),
    justifyContent: 'space-between',
    paddingInline: theme.spacing(1.25)
  },
  reducePanel: {
    display: 'flex',
    justifyContent: 'flex-end',
    margin: theme.spacing(1.5, 4),
    padding: 0
  },
  reducePanelButton: {
    color: theme.palette.text.primary,
    fontSize: theme.typography.body1.fontSize,
    fontWeight: theme.typography.fontWeightRegular,
    height: 'initial',
    padding: 0
  }
}));

export default useStyles;
