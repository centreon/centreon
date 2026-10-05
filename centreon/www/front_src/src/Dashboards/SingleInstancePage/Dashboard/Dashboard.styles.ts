import { makeStyles } from 'tss-react/mui';

export const useDashboardStyles = makeStyles()((theme) => ({
  body: {
    '& > *': {
      flex: 1,
      minHeight: 0
    },
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    marginLeft: theme.spacing(-2),
    marginRight: theme.spacing(-2),
    marginTop: theme.spacing(1.5),
    overflow: 'hidden'
  },
  divider: {
    borderStyle: 'dashed'
  },
  editActions: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  headerActionButton: {
    '&:hover': {
      backgroundColor: theme.palette.action.hover
    },
    alignItems: 'center',
    backgroundColor: theme.palette.background.paper,
    border: `1px solid ${theme.palette.divider}`,
    borderRadius: '6px',
    color: theme.palette.primary.main,
    display: 'inline-flex',
    justifyContent: 'center'
  },
  headerActionsRow: {
    '& > span': {
      display: 'flex',
      gap: theme.spacing(1)
    },
    '&[data-fullscreen="true"]': {
      '&:hover': {
        opacity: 1
      },
      opacity: 0,
      transition: theme.transitions.create('opacity')
    },
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(2),
    justifyContent: 'flex-end'
  },
  headerRow: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between'
  },
  titleDescription: {
    color: theme.palette.header.page.description,
    fontSize: '14px',
    maxWidth: '420px',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap'
  },
  titleGroup: {
    alignItems: 'baseline',
    display: 'flex',
    gap: theme.spacing(1.5),
    minWidth: 0
  },
  titleSeparator: {
    alignSelf: 'center',
    borderColor: theme.palette.header.page.border,
    height: '16px'
  },
  titleText: {
    color: theme.palette.header.page.title,
    fontSize: '22px',
    fontWeight: 700,
    whiteSpace: 'nowrap'
  }
}));
