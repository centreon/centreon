import { makeStyles } from 'tss-react/mui';

export const useWidgetPropertiesStyles = makeStyles()((theme) => ({
  previewPanelContainer: {
    height: '400px',
    padding: theme.spacing(1),
    width: '100%'
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
