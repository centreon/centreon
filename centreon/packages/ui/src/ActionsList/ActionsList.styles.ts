import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

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

    '&:hover': {
      '& .MuiListItemIcon-root': {
        color: getColor({ status: 'info', theme })
      }
    },
    '&[data-variant="error"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'error', theme })
        }
      }
    },
    '&[data-variant="info"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'info', theme })
        }
      }
    },

    '&[data-variant="pending"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'pending', theme })
        }
      }
    },

    '&[data-variant="primary"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'primary', theme })
        }
      }
    },

    '&[data-variant="secondary"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'secondary', theme })
        }
      }
    },

    '&[data-variant="success"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'success', theme })
        }
      }
    },

    '&[data-variant="warning"]': {
      '&:hover': {
        '& .MuiListItemIcon-root': {
          color: getColor({ status: 'warning', theme })
        }
      }
    }
  },
  list: {
    width: '100%'
  }
}));
