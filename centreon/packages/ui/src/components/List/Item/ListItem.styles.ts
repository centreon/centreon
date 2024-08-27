import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  avatar: {
    '&, &.MuiListItemAvatar-root, & > div': {
      color: theme.palette.primary.contrastText,
      fontSize: '1rem',
      height: '2.25rem',
      width: '2.25rem'
    },
    disabled: {
      backgroundColor: theme.palette.action.disabled
    },
    minWidth: 'unset'
  },
  listItem: {
    alignItems: 'center',
    display: 'flex',
    flexGrow: 1,
    gap: theme.spacing(2),
    overflow: 'hidden',
    paddingBottom: theme.spacing(1),
    paddingTop: theme.spacing(1),
    width: '100%'
  },
  secondary: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  text: {
    '&, &.MuiListItemText-root': {
      '& .MuiListItemText-primary': {
        fontSize: '0.875rem'
      },
      '& .MuiListItemText-secondary': {
        fontSize: '0.625rem'
      },
      '& > *': {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        textWrap: 'nowrap'
      },
      margin: 0
    },
    disabled: {
      color: theme.palette.action.disabled
    }
  },
  textSkeleton: {
    '& span:nth-child(1)': {
      fontSize: '0.875rem'
    },
    '& span:nth-child(2)': {
      fontSize: '0.625rem',
      width: '60%'
    },
    display: 'flex',
    flexDirection: 'column',
    minWidth: '40%'
  }
}));
