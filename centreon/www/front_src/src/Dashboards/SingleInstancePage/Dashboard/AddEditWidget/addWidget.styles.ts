import { makeStyles } from 'tss-react/mui';

export const useAddWidgetStyles = makeStyles()((theme) => ({
  container: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: 'minmax(320px, 1fr) 2fr'
  },
  preview: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'center',
    minHeight: theme.spacing(45)
  },
  widgetAvatar: {
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white
  },
  widgetProperties: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2),
    height: '100%'
  },
  widgetPropertiesContent: {
    bottom: 0,
    height: '100%',
    left: 0,
    minHeight: '70vh',
    overflow: 'auto',
    position: 'absolute',
    right: 0,
    top: 0
  },
  widgetPropertiesContentContainer: {
    height: '100%',
    position: 'relative'
  }
}));
