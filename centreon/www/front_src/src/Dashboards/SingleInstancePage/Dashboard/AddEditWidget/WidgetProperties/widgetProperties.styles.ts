import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useWidgetPropertiesStyles = makeStyles()((theme) => ({
  groupContent: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    marginLeft: theme.spacing(0.5)
  },
  groupDivider: {
    marginBottom: theme.spacing(1)
  },
  groupTitle: {
    fontWeight: theme.typography.fontWeightBold
  },
  previewHeading: {
    display: 'block'
  },
  previewPanelContainer: {
    height: '400px',
    [theme.breakpoints.down('sm')]: {
      height: '200px'
    },
    padding: theme.spacing(1.5),
    position: 'relative',
    width: '100%'
  },
  previewTitle: {
    fontSize: '1.3rem',
    fontWeight: theme.typography.fontWeightBold,
    marginBottom: theme.spacing(1),
    padding: theme.spacing(0)
  },
  previewUserRightPanel: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.common.black, 0.6),
    bottom: 0,
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0
  },
  previewUserRightPanelContent: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  widgetDataContent: {
    display: 'flex',
    flexDirection: 'row',
    [theme.breakpoints.down('lg')]: {
      flexDirection: 'column'
    },
    gap: theme.spacing(2),
    maxHeight: '27vh',
    overflow: 'auto'
  },
  widgetDataItem: {
    width: '100%'
  },
  widgetDescription: {
    marginBottom: theme.spacing(1)
  },
  widgetProperties: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  widgetPropertiesContainer: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius
  }
}));

export const useWidgetSelectionStyles = makeStyles()((theme) => ({
  selectField: {
    flexGrow: 1
  },
  widgetIcon: {
    background: theme.palette.primary.main,
    borderRadius: theme.shape.borderRadius,
    fill: 'white',
    height: '60px',
    marginRight: theme.spacing(1),
    width: '60px'
  },
  widgetSelection: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    width: '100%'
  }
}));
