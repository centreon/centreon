import { makeStyles } from 'tss-react/mui';

export const usePreviewStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  },
  label: {
    color: theme.palette.action.disabled
  }
}));

export const useIframeStyles = makeStyles()({
  container: {
    height: '98%',
    width: '100%'
  },
  iframe: {
    width: '100%',
    height: '100%',
    border: 'none'
  }
});
