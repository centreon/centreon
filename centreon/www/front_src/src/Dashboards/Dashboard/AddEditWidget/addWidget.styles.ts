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
    height: '66vh',
    padding: theme.spacing(1),
    overflow: 'auto'
  },
  widgetDataset: {
    height: '20vh',
    overflow: 'auto'
  }
}));
