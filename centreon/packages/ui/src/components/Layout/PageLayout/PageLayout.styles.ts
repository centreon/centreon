import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  pageLayout: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%'
  },
  pageLayoutActions: {
    display: 'flex',
    gap: theme.spacing(0.5),
    paddingBottom: theme.spacing(2.5)
  },
  pageLayoutBody: {
    '&[data-has-background="true"]': {
      backgroundColor: theme.palette.layout.body.background
    },
    padding: theme.spacing(3, 4, 5)
  },
  pageLayoutHeader: {
    '[data-variant="fixed-header"] &': {
      backgroundColor: theme.palette.layout.header.background,
      borderBottom: `1px solid ${theme.palette.layout.header.border}`,
      padding: theme.spacing(5, 4, 2),
      position: 'sticky',
      top: 0,
      zIndex: `calc(${theme.zIndex.mobileStepper} - 100)`
    },

    padding: theme.spacing(5, 4, 0)
  }
}));
