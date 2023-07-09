import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  contactAccessRightsList: {
    borderBottom: `1px solid ${theme.palette.divider}`,
    borderTop: `1px solid ${theme.palette.divider}`,
    maxHeight: '29.5rem',
    maxWidth: '520px',
    overflowY: 'auto'
  },
  contactAccessRightsListEmpty: {
    alignItems: 'center',
    color: theme.palette.text.secondary,
    fontSize: '0.75rem',
    padding: theme.spacing(2, 8),
    textAlign: 'center'
  },
  contactAccessRightsListItem: {
    '& .MuiChip-root': {
      '& .MuiChip-label': {
        lineHeight: 'unset',
        padding: theme.spacing(0, 0.75),
        textOverflow: 'unset'
      },
      '&[data-state="added"]': {
        borderColor: theme.palette.chip.color.success,
        color: theme.palette.chip.color.success
      },
      '&[data-state="removed"]': {
        borderColor: theme.palette.chip.color.error,
        color: theme.palette.chip.color.error
      },
      '&[data-state="updated"]': {
        borderColor: theme.palette.chip.color.info,
        color: theme.palette.chip.color.info
      },
      borderRadius: '0.625rem',
      flexBasis: '3.5rem',
      flexShrink: 0,
      fontSize: '0.6875rem',

      fontWeight: 500,
      height: 'auto',
      lineHeight: '1.125rem',
      minHeight: 'unset',
      textTransform: 'lowercase'
    },
    '& .MuiListItemAvatar-root': {
      '& > div': {
        color: theme.palette.primary.contrastText,
        fontSize: '1rem',
        height: '2.25rem',
        width: '2.25rem'
      },
      minWidth: 'unset'
    },
    '& .MuiListItemText-root': {
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
    '& > span:first-of-type': {
      alignItems: 'center',
      display: 'flex',
      flexGrow: 1,
      gap: theme.spacing(2),
      overflow: 'hidden'
    },
    '&[data-is-removed="true"]': {
      '& > span:first-of-type': {
        opacity: 0.3
      }
    },
    display: 'flex',

    flexGrow: 1,
    gap: theme.spacing(3),
    justifyContent: 'space-between',

    maxWidth: '520px',
    paddingBottom: theme.spacing(1),
    paddingRight: theme.spacing(7),
    paddingTop: theme.spacing(1)
  }
}));

export { useStyles };
