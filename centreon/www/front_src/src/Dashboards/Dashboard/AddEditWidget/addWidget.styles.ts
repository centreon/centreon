import { makeStyles } from 'tss-react/mui';

export const useAddWidgetStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateAreas: `
      "widgetProperties preview"
      "widgetProperties widgetData"
    `,
    gridTemplateColumns: '1fr 2fr',
    gridTemplateRows: 'minmax(300px, auto) auto'
  },
  preview: {
    alignItems: 'center',
    display: 'flex',
    gridArea: 'preview',
    justifyContent: 'center'
  },
  widgetAvatar: {
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white
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
