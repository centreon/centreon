import { useRefreshInterval } from '@centreon/ui';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Preview from './Preview';
import type { WebPageProps } from './models';
import { labelWebpageDisplay } from './translatedLabels';
import { useIframeStyles } from './useWebPage.styles';

const getIframeSrc = (url: string) =>
  /^http/.test(url) ? url : `http://${url}`;

const refreshIframe = (id) => {
  const iframe = document.getElementById(`Webpage_${id}`);

  if (iframe) {
    // biome-ignore lint/correctness/noSelfAssign: <explanation>
    iframe.src = iframe?.src;
  }
};

const WebPage = ({
  panelOptions,
  globalRefreshInterval,
  id: widgetId
}: WebPageProps): JSX.Element => {
  const { classes } = useIframeStyles();
  const { t } = useTranslation();

  const [intervalId, setIntervalId] = useState(null);

  const { url, refreshInterval, refreshIntervalCustom } = panelOptions;

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  useEffect(() => {
    if (!refreshIntervalToUse) {
      if (intervalId) {
        clearInterval(intervalId);
        setIntervalId(null);
      }
      return;
    }

    if (intervalId) {
      clearInterval(intervalId);
    }

    const id = setInterval(() => refreshIframe(widgetId), refreshIntervalToUse);
    setIntervalId(id);

    return () => clearInterval(id);
  }, [refreshIntervalToUse]);

  if (!url) {
    return <Preview />;
  }

  return (
    <div className={classes.container}>
      <iframe
        src={getIframeSrc(url)}
        className={classes.iframe}
        title={t(labelWebpageDisplay)}
        id={`Webpage_${widgetId}`}
        data-testid={labelWebpageDisplay}
      />
    </div>
  );
};

export default WebPage;
