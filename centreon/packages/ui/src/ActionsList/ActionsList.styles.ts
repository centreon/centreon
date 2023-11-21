import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { Theme, alpha } from '@mui/material';

interface GetBackgroundAndColorProps {
  status: string;
  theme: Theme;
}

const getColor = ({ theme, status }: GetBackgroundAndColorProps): string =>
  equals(theme.palette.mode, 'dark')
    ? theme.palette.common.white
    : theme.palette[status].main;

export const useStyles = makeStyles()((theme) => ({
  item: {
    '& .MuiListItemText-secondary': {
      textWrap: 'pretty'
    },
    '&[data-variant="error"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'error', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.error.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'error', theme })
      },
      color: theme.palette.error.main
    },
    '&[data-variant="info"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'info', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.info.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'info', theme })
      },
      color: theme.palette.info.main
    },
    '&[data-variant="pending"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'pending', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.pending.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'pending', theme })
      },
      color: theme.palette.pending.main
    },
    '&[data-variant="primary"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'primary', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.primary.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'primary', theme })
      },
      color: theme.palette.primary.main
    },
    '&[data-variant="secondary"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'secondary', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.secondary.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'secondary', theme })
      },
      color: theme.palette.secondary.main
    },
    '&[data-variant="success"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'success', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.success.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'success', theme })
      },
      color: theme.palette.success.main
    },
    '&[data-variant="warning"]': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'warning', theme })
      },
      '&:hover': {
        background: alpha(
          theme.palette.warning.main,
          theme.palette.action.focusOpacity
        ),
        color: getColor({ status: 'warning', theme })
      },
      color: theme.palette.warning.main
    }
  },
  list: {
    width: '100%'
  }
}));
