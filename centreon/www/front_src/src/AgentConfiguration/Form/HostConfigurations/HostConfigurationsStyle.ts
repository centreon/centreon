import { makeStyles } from 'tss-react/mui';

export const useHostConfigurationsStyle = makeStyles()((theme) => ({
  addButton: {
    width: '100%'
  },
  deleteContainer: {
    height: '100%',
    borderTop: `1px solid ${theme.palette.divider}`,
    borderBottom: `1px solid ${theme.palette.divider}`,
    borderRight: `1px solid ${theme.palette.divider}`,
    borderRadius: `${theme.shape.borderRadius}px`,
    width: theme.spacing(2),
    position: 'absolute',
    top: 0,
    right: 0
  },
  deleteButton: {
    position: 'absolute',
    top: 'calc(50% - 16px)',
    right: '-16px',
    backgroundColor: theme.palette.background.paper
  },
  hostConfigurations: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    overflowY: 'auto',
    maxHeight: '210px'
  }
}));
