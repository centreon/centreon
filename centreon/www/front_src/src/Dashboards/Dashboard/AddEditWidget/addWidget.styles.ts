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
  widgetDataset: {
    height: '37vh',
    [theme.breakpoints.down('xl')]: {
      height: '23vh'
    },
    [theme.breakpoints.down('lg')]: {
      height: '20vh'
    },
    overflow: 'auto'
  },
  widgetProperties: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  widgetPropertiesContent: {
    backgroundColor: theme.palette.background.default,
    borderRadius: theme.shape.borderRadius,
    height: '68vh',
    overflow: 'auto',
    padding: theme.spacing(1)
  }
}));
