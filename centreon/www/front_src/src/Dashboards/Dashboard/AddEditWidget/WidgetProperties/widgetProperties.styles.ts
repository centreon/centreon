import { makeStyles } from 'tss-react/mui';

export const useWidgetPropertiesStyles = makeStyles()((theme) => ({
  previewPanel: {
    height: '100%',
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
  }
}));
