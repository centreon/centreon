import { makeStyles } from 'tss-react/mui';

export const useInputStyles = makeStyles()((theme) => ({
  addDatasetButton: {
    width: '33%'
  },
  contactsAndContactGroups: {
    display: 'flex',
    flexDirection: 'column',
    width: '50%'
  },
  resourceSelection: {
    display: 'flex',
    flexDirection: 'column',
    width: '50%'
  },
  ruleProperties: {
    backgroundColor: theme.palette.grey[300],
    borderRadius: '4px',
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(2),
    width: '45%'
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
    lineHeight: 1,
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
