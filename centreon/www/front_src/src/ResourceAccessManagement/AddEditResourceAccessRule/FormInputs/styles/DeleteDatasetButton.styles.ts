import { makeStyles } from 'tss-react/mui';

export const useDeleteDatasetButtonStyles = makeStyles()((theme) => ({
  deleteDatasetButtonContainer: {
    alignItems: 'center',
    borderBottom: `2px solid ${theme.palette.divider}`,
    borderRight: `2px solid ${theme.palette.divider}`,
    borderTop: `2px solid ${theme.palette.divider}`,
    display: 'flex',
    marginBottom: theme.spacing(6),
    marginLeft: theme.spacing(4),
    marginRight: theme.spacing(4),
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
