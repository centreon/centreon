import Preview from './Preview';
import { useIframeStyles } from './useWebPage.styles';


const getIframeSrc = (url:string) =>  /^http/.test(url) ? url : `http://${url}`;

const WebPage = ({ panelOptions }): JSX.Element => {
  const { classes } = useIframeStyles();

  const { url } = panelOptions;

  if (!url) {
    return <Preview />;
  }

  return (
    <div className={classes.container}>
      <iframe
        src={getIframeSrc(url)}
        className={classes.iframe}
        title="Webpage Display"
        data-testid="Webpage Display"
      />
    </div>
  );
};

export default WebPage;
