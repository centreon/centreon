import Preview from './Preview';
import { useIframeStyles } from './useWebPage.styles';

const WebPage = ({ panelOptions }): JSX.Element => {
  const { classes } = useIframeStyles();

  const { url } = panelOptions;

  if (!url) {
    return <Preview />;
  }

  const iframeSrc = /^http/.test(url) ? url : `http://${url}`;

  return (
    <div className={classes.container}>
      <iframe
        src={iframeSrc}
        className={classes.iframe}
        title="Webpage Display"
        data-testid="Webpage Display"
      />
    </div>
  );
};

export default WebPage;
