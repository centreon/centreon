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
    gridTemplateRows: '200px auto'
  },
  preview: {
    alignItems: 'center',
    display: 'flex',
    gridArea: 'preview',
    justifyContent: 'center'
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
    height: '100%',
    padding: theme.spacing(1)
  }
}));
