import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  pageLayout: {
    display: 'grid',
    gridTemplateRows: 'auto 1fr',
    overflow: 'hidden',
    height: '100%'
  },
  pageLayoutActions: {
    '& > span': {
      display: 'flex',
      gap: theme.spacing(0.5)
    },
    '&[data-row-reverse="true"]': {
      flexDirection: 'row-reverse'
    },
    display: 'flex',
    justifyContent: 'space-between',
    paddingBottom: theme.spacing(2.5)
  },
  pageLayoutBody: {
    '&[data-has-background="true"]': {
      backgroundColor: theme.palette.layout.body.background
    },
    '&[data-has-actions="true"]': {
      gridTemplateRows: 'min-content auto'
    },
    display: 'grid',
    gridTemplateRows: 'auto',
    overflow: 'hidden',
    padding: theme.spacing(1.5, 3, 5)
  },
  pageLayoutHeader: {
    '[data-variant="fixed-header"] &': {
      backgroundColor: theme.palette.layout.header.background,
      borderBottom: `1px solid ${theme.palette.layout.header.border}`,
      padding: theme.spacing(3, 4, 2),
      position: 'sticky',
      top: 0,
      zIndex: `calc(${theme.zIndex.mobileStepper} - 100)`
    },
    padding: theme.spacing(3, 3, 0)
  }
}));
