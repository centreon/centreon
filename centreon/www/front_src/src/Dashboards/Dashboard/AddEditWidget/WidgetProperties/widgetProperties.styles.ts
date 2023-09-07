import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useWidgetPropertiesStyles = makeStyles()((theme) => ({
  previewPanelContainer: {
    height: '400px',
    padding: theme.spacing(1),
    position: 'relative',
    width: '100%'
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
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: '1fr 1fr'
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
  }
}));

export const useWidgetSelectionStyles = makeStyles()((theme) => ({
  selectField: {
    flexGrow: 1
  },
  widgetSelection: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    width: '100%'
  }
}));
