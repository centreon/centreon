import { makeStyles } from 'tss-react/mui';

export const useWidgetPropertiesStyles = makeStyles()((theme) => ({
  previewPanel: {
    height: '100%',
    padding: theme.spacing(1),
    width: '100%'
  }
}));
