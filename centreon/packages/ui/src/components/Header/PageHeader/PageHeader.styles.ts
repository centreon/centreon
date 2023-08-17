import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  header: {
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    flexDirection: 'row',
    h1: {
      font: 'normal normal 600 24px/24px Roboto',
      letterSpacing: '0.18px',
      margin: theme.spacing(0, 0, 1.5, 0)
    },
    justifyContent: 'space-between',

    nav: {
      display: 'flex',
      gap: theme.spacing(1),
      justifyContent: 'flex-end'
    },

    padding: theme.spacing(0, 0, 1.5, 0)
  },
  pageHeader: {
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.palette.header.page.border}`,
    display: 'flex',
    gap: theme.spacing(4),
    paddingBottom: theme.spacing(2)
  },
  pageHeaderActions: {
    '& > button': {
      '&:hover': {
        backgroundColor: theme.palette.header.page.action.background.active,
        color: theme.palette.header.page.action.color.active
      },
      backgroundColor: theme.palette.header.page.action.background.default,

      color: theme.palette.header.page.action.color.default
    },
    display: 'flex',

    gap: theme.spacing(2)
  },
  pageHeaderMain: {
    display: 'flex',
    flexGrow: 1,
    gap: theme.spacing(1)
  },
  pageHeaderMenu: {
    alignItems: 'flex-start',
    display: 'flex',
    position: 'relative',
    transform: `translateY(-${theme.spacing(0.25)})`
  },
  pageHeaderTitle: {
    '& > *': {
      display: 'grid'
    },
    '& > span': {
      display: 'flex',
      flexDirection: 'row',

      gap: theme.spacing(2)
    },
    display: 'flex',

    flexDirection: 'column',

    gap: theme.spacing(1),

    h1: {
      color: theme.palette.header.page.title,
      font: 'normal normal 700 24px/100% Roboto',
      letterSpacing: '0.15px',
      margin: theme.spacing(0)
    }
  },
  pageHeaderTitleActions: {
    '& > button': {
      opacity: 0.2,
      padding: 0
    },
    alignItems: 'bottom',
    display: 'flex',
    gap: theme.spacing(1)
  },
  pageHeaderTitleDescription: {
    color: theme.palette.header.page.description,
    font: 'normal normal 400 12px/16px Roboto',
    letterSpacing: '0.15px',
    maxWidth: '560px',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

export { useStyles };
