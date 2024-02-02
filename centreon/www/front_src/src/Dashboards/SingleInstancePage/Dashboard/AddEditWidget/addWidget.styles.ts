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
  widgetPropertiesContent: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    bottom: 0,
    height: '68vh',
    left: 0,
    overflow: 'auto',
    padding: theme.spacing(1),
    position: 'absolute',
    right: 0,
    top: 0
  },
  widgetPropertiesContentContainer: {
    position: 'relative'
  }
}));
