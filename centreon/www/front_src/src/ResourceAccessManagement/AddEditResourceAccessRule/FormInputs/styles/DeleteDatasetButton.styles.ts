import { makeStyles } from 'tss-react/mui';

export const useDeleteDatasetButtonStyles = makeStyles()((theme) => ({
  deleteDatasetButtonContainer: {
    alignItems: 'center',
    borderBottom: `2px solid ${theme.palette.divider}`,
    borderRight: `2px solid ${theme.palette.divider}`,
    borderTop: `2px solid ${theme.palette.divider}`,
    display: 'flex',
    marginBottom: theme.spacing(7.25),
    marginLeft: theme.spacing(4),
    marginRight: theme.spacing(4),
    marginTop: theme.spacing(1),
    width: theme.spacing(0.75)
  },
  deleteIcon: {
    color: theme.palette.common.white,
    margin: '0px'
  },
  deleteIconChip: {
    '&:hover': {
      backgroundColor: theme.palette.primary.dark
    },
    backgroundColor: theme.palette.primary.main,
    position: 'relative',
    right: theme.spacing(0.6)
  }
}));
