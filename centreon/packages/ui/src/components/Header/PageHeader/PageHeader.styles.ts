import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

const useStyles = makeStyles()((theme) => ({
  header: {
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    flexDirection: 'row',
    h1: {
      ...theme.typography.h5,
      fontWeight: theme.typography.fontWeightMedium,
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
    alignItems: 'center',
    borderBottom: `1px solid ${theme.palette.header.page.border}`,
    display: 'flex',
    gap: theme.spacing(4),
    paddingBottom: theme.spacing(1)
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
    transform: `translateY(-${theme.spacing(0.25)})`,
    alignSelf: 'center'
  },
  pageHeaderMessage: {
    alignItems: 'center',
    color: theme.palette.warning.main,
    display: 'flex',
    gap: theme.spacing(1)
  },
  pageHeaderMessageIcon: {
    alignItems: 'center',
    background: theme.palette.warning.main,
    borderRadius: '50%',
    color: theme.palette.common.white,
    display: 'flex',
    height: theme.spacing(2.5),
    justifyContent: 'center',
    width: theme.spacing(2.5)
  },
  pageHeaderMessageIconWrapper: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.warning.main, 0.7),
    borderRadius: '50%',
    display: 'flex',
    height: theme.spacing(3.5),
    justifyContent: 'center',
    width: theme.spacing(3.5)
  },
  pageHeaderTitle: {
    '& > *': {
      display: 'grid'
    },
    '& > span': {
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(2),
      alignItems: 'center'
    },
    display: 'flex',
    flexDirection: 'column',
    alignSelf: 'flex-start',
    h1: {
      ...theme.typography.h6,
      fontWeight: theme.typography.fontWeightBold,
      margin: theme.spacing(0),
      lineHeight: '1'
    }
  },
  pageHeaderTitleActions: {
    '& > button': {
      opacity: 0.2,
      padding: 0
    },
    alignItems: 'bottom',
    display: 'flex',
    gap: theme.spacing(1),
    paddingTop: theme.spacing(0.5)
  },
  pageHeaderTitleDescription: {
    color: theme.palette.header.page.description,
    maxWidth: '560px',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
    lineHeight: '1.8'
  }
}));

export { useStyles };
