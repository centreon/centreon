import { makeStyles } from 'tss-react/mui';

export const useResourceStyles = makeStyles()((theme) => ({
  resourceType: {
    borderRadius: `${theme.shape.borderRadius}px 0px 0px ${theme.shape.borderRadius}px`,
    width: '136px'
  },
  resources: {
    '& .MuiInputBase-root': {
      borderRadius: `0px ${theme.shape.borderRadius}px ${theme.shape.borderRadius}px 0px`
    },
    flexGrow: 1,
    maxWidth: '272px'
  },
  resourcesContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  }
}));
