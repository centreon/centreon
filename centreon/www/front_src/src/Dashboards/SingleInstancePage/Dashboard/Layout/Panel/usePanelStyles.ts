import { alpha } from '@mui/material';

import { makeStyles } from 'tss-react/mui';

export const usePanelHeaderStyles = makeStyles()((theme) => ({
  description: {
    marginBottom: theme.spacing(1)
  },
  descriptionInput: {
    overflow: 'hidden',
    textOverflow: 'clip',
    whiteSpace: 'nowrap'
  },
  panelActionsIcons: {
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    marginRight: theme.spacing(1)
  },
  panelContent: {
    height: '100%',
    overflow: 'auto'
  },
  panelContentWithDescription: {
    height: `calc(100% - ${theme.spacing(2.75)})`,
    overflow: 'auto'
  },
  panelHeader: {
    '& span': {
      fontSize: theme.typography.body1.fontSize,
      fontWeight: theme.typography.fontWeightMedium,
      lineHeight: 1
    },
    gap: theme.spacing(1),
    height: theme.spacing(4.5),
    padding: theme.spacing(0),
    paddingTop: theme.spacing(1.25)
  },
  panelHeaderContent: {
    marginTop: '-8px',
    width: '45%'
  },
  panelLastRefresh: {
    color: theme.palette.text.disabled,
    cursor: 'pointer',
    fontSize: '0.65rem',
    whiteSpace: 'nowrap'
  },
  panelResourceLink: {
    '&:hover': {
      boxShadow: `0 0 0 4px ${alpha(theme.palette.primary.main, 0.15)}`
    },
    borderRadius: '50%',
    display: 'inline-flex',
    opacity: 0,
    pointerEvents: 'none',
    transition: theme.transitions.create(['opacity', 'box-shadow'])
  },
  panelTitle: {
    fontSize: '1.1rem',
    fontWeight: theme.typography.fontWeightMedium,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
    width: '100%'
  }
}));

export const useAddWidgetPanelStyles = makeStyles()((theme) => ({
  addWidgetPanel: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'space-evenly',
    margin: theme.spacing(1, 2)
  },
  avatar: {
    alignSelf: 'center',
    backgroundColor: theme.palette.primary.main,
    height: theme.spacing(10),
    width: theme.spacing(10)
  }
}));
