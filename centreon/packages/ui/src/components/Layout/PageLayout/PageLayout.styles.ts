import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  pageLayout: {
    display: 'grid',
    gridTemplateRows: 'min-content',
    overflow: 'hidden'
  },
  pageLayoutActions: {
    '& > span': {
      display: 'flex',
      gap: theme.spacing(0.5)
    },

    display: 'flex',
    justifyContent: 'space-between',
    paddingBottom: theme.spacing(2.5)
  },
  pageLayoutBody: {
    '&[data-has-background="true"]': {
      backgroundColor: theme.palette.layout.body.background
    },
    display: 'grid',
    gridTemplateRows: 'min-content',
    overflow: 'hidden',
    padding: theme.spacing(3, 3, 5)
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

    padding: theme.spacing(5, 3, 0)
  }
}));
