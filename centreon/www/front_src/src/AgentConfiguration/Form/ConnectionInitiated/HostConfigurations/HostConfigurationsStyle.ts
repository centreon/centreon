import { makeStyles } from 'tss-react/mui';

export const useHostConfigurationsStyle = makeStyles()((theme) => ({
  addButton: {
    width: '100%'
  },
  deleteButton: {
    position: 'absolute',
    right: '-16px',
    top: 'calc(50% - 16px)'
  },
  deleteContainer: {
    borderBottom: `1px solid ${theme.palette.divider}`,
    borderRadius: `${theme.shape.borderRadius}px`,
    borderRight: `1px solid ${theme.palette.divider}`,
    borderTop: `1px solid ${theme.palette.divider}`,
    height: '50%',
    position: 'absolute',
    right: 0,
    top: 15,
    width: theme.spacing(2)
  },
  deleteIcon: {
    '&:hover': {
      color: theme.palette.error.main
    },
    color: theme.palette.action.disabled
  },
  divider: {
    borderStyle: 'dashed',
    marginBottom: theme.spacing(3),
    width: '90%'
  },
  hostConfigurations: {
    display: 'flex',
    flexDirection: 'column',
    maxHeight: '210px',
    overflowY: 'auto',
    paddingTop: theme.spacing(0.75)
  },
  input: {
    backgroundColor: theme.palette.background.default
  }
}));

export const useAddButtonStyles = makeStyles()((theme) => ({
  addButton: {
    borderRadius: theme.spacing(2),
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2),
    height: theme.spacing(4),
    paddingRight: theme.spacing(1)
  },
  addButtonDivider: {
    width: '95%'
  }
}));
