import { makeStyles } from 'tss-react/mui';

export const useAddWidgetStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateAreas: `
      "preview widgetProperties"
      "widgetData widgetProperties"
    `,
    gridTemplateColumns: '2fr 1fr',
    gridTemplateRows: 'auto auto'
  },
  preview: {
    alignItems: 'center',
    display: 'flex',
    gridArea: 'preview',
    justifyContent: 'center',
    minHeight: '200px'
  },
  widgetData: {
    gridArea: 'widgetData'
  },
  widgetProperties: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    gridArea: 'widgetProperties'
  },
  widgetPropertiesContent: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    height: '100%',
    padding: theme.spacing(1)
  }
}));
