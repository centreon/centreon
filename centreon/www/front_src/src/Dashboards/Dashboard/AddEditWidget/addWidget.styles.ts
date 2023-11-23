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
    minHeight: theme.spacing(45)
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
  widgetPropertiesContentContainer: {
    position: 'relative'
  },
  widgetPropertiesContent: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    height: '68vh',
    overflow: 'auto',
    padding: theme.spacing(1)
  }
}));
