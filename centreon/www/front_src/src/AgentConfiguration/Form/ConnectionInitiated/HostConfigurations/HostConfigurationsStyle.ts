import { makeStyles } from 'tss-react/mui';

export const useHostConfigurationsStyle = makeStyles()((theme) => ({
  addButton: {
    width: '100%'
  },
  deleteContainer: {
    height: '50%',
    borderTop: `1px solid ${theme.palette.divider}`,
    borderBottom: `1px solid ${theme.palette.divider}`,
    borderRight: `1px solid ${theme.palette.divider}`,
    borderRadius: `${theme.shape.borderRadius}px`,
    width: theme.spacing(2),
    position: 'absolute',
    top: 15,
    right: 0
  },
  deleteButton: {
    position: 'absolute',
    top: 'calc(50% - 16px)',
    right: '-16px'
  },
  deleteIcon: {
    color: theme.palette.action.disabled,
    '&:hover': {
      color: theme.palette.error.main
    }
  },
  hostConfigurations: {
    paddingTop: theme.spacing(0.75),
    display: 'flex',
    flexDirection: 'column',
    overflowY: 'auto',
    maxHeight: '210px'
  },
  input: {
    backgroundColor: theme.palette.background.default
  },
  divider: {
    borderStyle: 'dashed',
    marginBottom: theme.spacing(3),
    width: '90%'
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
