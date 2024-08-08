import { makeStyles } from 'tss-react/mui';

export const useParameterStyles = makeStyles()((theme) => ({
  parameterComposition: {
    height: 'auto',
    marginBottom: theme.spacing(1.5),
    overflow: 'auto',
    paddingTop: theme.spacing(1),
    width: '100%'
  },
  parameterCompositionItem: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  },
  parameterItem: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: `${theme.spacing(22)} 1fr`,
    marginBottom: theme.spacing(0.5)
  }
}));

export const useDeleteButtonStyles = makeStyles()((theme) => ({
  deleteButtonContainer: {
    alignItems: 'center',
    borderBottom: `2px solid ${theme.palette.divider}`,
    borderRight: `2px solid ${theme.palette.divider}`,
    borderTop: `2px solid ${theme.palette.divider}`,
    display: 'flex',
    marginBottom: theme.spacing(2),
    marginLeft: theme.spacing(4),
    marginRight: theme.spacing(1),
    marginTop: theme.spacing(0.5),
    width: theme.spacing(1)
  },
  deleteIcon: {
    color: theme.palette.common.white,
    margin: theme.spacing(0)
  },
  deleteIconChip: {
    '&:hover': {
      backgroundColor: theme.palette.chip.color.error
    },
    backgroundColor: theme.palette.divider,
    position: 'relative',
    right: theme.spacing(0.5)
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

export const useParametersStyles = makeStyles()((theme) => ({
  parametersComposition: {
    display: 'flex'
  },
  parametersContainer: {
    display: 'flex',
    flexDirection: 'column'
  },
  parametersDivider: {
    borderStyle: 'dashed',
    marginBottom: theme.spacing(3),
    width: '90%'
  }
}));
