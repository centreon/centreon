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
    border: 'none',
    height: '100%',
    width: '100%'
  }
});
