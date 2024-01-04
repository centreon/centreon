import { makeStyles } from 'tss-react/mui';

export const useInputStyles = makeStyles()((theme) => ({
  addDatasetButton: {
    marginTop: theme.spacing(4),
    width: '33%'
  },
  contactsAndContactGroups: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%'
  },
  resourceSelection: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'flex-start',
    width: '100%'
  },
  ruleProperties: {
    backgroundColor: theme.palette.grey[300],
    borderRadius: '4px',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'flex-start',
    padding: theme.spacing(2)
  },
  titleGroup: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium
  }
}));

export const useResourceDatasetStyles = makeStyles()((theme) => ({
  resourceComposition: {
    [theme.breakpoints.down('xl')]: {
      height: '21vh'
    },
    [theme.breakpoints.down('lg')]: {
      height: '20vh'
    },
    height: '27vh',
    overflow: 'auto',
    paddingTop: theme.spacing(1),
    width: '100%'
  },
  resourceCompositionItem: {
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(20)} 1fr`
  },
  resourceTitle: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    paddingBottom: theme.spacing(0.5)
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
    gap: theme.spacing(1)
  },
  resourcesHeader: {
    display: 'flex',
    gap: theme.spacing(1),
    width: '100%'
  }
}));
