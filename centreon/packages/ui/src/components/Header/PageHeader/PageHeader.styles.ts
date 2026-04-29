import { alpha } from '@mui/system';

import type { CSSObject } from 'tss-react';
import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  header: {
    '& h1': {
      fontFamily: theme.typography.h5.fontFamily,
      fontSize: theme.typography.h5.fontSize,
      fontWeight: theme.typography.fontWeightMedium,
      letterSpacing: theme.typography.h5.letterSpacing,
      lineHeight: theme.typography.h5.lineHeight,
      margin: theme.spacing(0, 0, 1.5, 0)
    } as CSSObject,

    '& nav': {
      display: 'flex',
      gap: theme.spacing(1),
      justifyContent: 'flex-end'
    } as CSSObject,
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',

    padding: theme.spacing(0, 0, 1.5, 0)
  },
  pageHeader: {
    alignItems: 'center',
    borderBottom: `2px solid ${theme.palette.header.page.border}`,
    display: 'flex',
    gap: theme.spacing(4),
    paddingBottom: theme.spacing(0.5)
  },
  pageHeaderActions: {
    '& > button': {
      '&:hover': {
        backgroundColor: theme.palette.header.page.action.background.active,
        color: theme.palette.header.page.action.color.active
      },
      backgroundColor: theme.palette.header.page.action.background.default,

      color: theme.palette.header.page.action.color.default
    } as CSSObject,
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
    alignSelf: 'center',
    display: 'flex',
    position: 'relative',
    transform: `translateY(-${theme.spacing(0.25)})`
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
    } as CSSObject,
    '& > span': {
      alignItems: 'center',
      display: 'flex',
      flexDirection: 'row',
      gap: theme.spacing(2)
    } as CSSObject,
    '& h1': {
      fontFamily: theme.typography.h5.fontFamily,
      fontSize: theme.typography.h5.fontSize,
      fontWeight: theme.typography.fontWeightBold,
      letterSpacing: theme.typography.h5.letterSpacing,
      lineHeight: '1',
      margin: theme.spacing(0)
    } as CSSObject,
    alignSelf: 'flex-start',
    display: 'flex',
    flexDirection: 'column'
  },
  pageHeaderTitleActions: {
    '& > button': {
      opacity: 0.2,
      padding: 0
    } as CSSObject,
    alignItems: 'bottom',
    display: 'flex',
    gap: theme.spacing(1),
    paddingTop: theme.spacing(0.5)
  },
  pageHeaderTitleDescription: {
    color: theme.palette.header.page.description,
    lineHeight: '1.8',
    maxWidth: '560px',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  }
}));

export { useStyles };
