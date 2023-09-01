import { makeStyles } from 'tss-react/mui';

export const useAddWidgetStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: '1fr 2fr'
  },
  preview: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
    minHeight: '400px'
  },
  previewAndDataset: {
    position: 'sticky',
    top: 0
  },
  widgetAvatar: {
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white
  },
  widgetProperties: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  widgetPropertiesContent: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    height: '100%',
    padding: theme.spacing(1)
  }
}));
