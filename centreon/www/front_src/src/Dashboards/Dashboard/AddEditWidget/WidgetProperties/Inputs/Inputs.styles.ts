import { makeStyles } from 'tss-react/mui';

export const useResourceStyles = makeStyles()((theme) => ({
  resourceCompositionItem: {
    display: 'grid',
    gridTemplateColumns: '136px 1fr'
  },
  resourceType: {
    borderRadius: `${theme.shape.borderRadius}px 0px 0px ${theme.shape.borderRadius}px`
  },
  resources: {
    '& .MuiInputBase-root': {
      borderRadius: `0px ${theme.shape.borderRadius}px ${theme.shape.borderRadius}px 0px`
    }
  },
  resourcesContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  resourcesHeader: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1),
    width: '100%'
  },
  resourcesHeaderAvatar: {
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    fontSize: theme.typography.body1.fontSize,
    height: theme.spacing(2),
    width: theme.spacing(2)
  },
  resourcesHeaderDivider: {
    flexGrow: 1
  }
}));
