import { useRefreshInterval } from '@centreon/ui';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Preview from './Preview';
import type { WebPageProps } from './models';
import { labelWebpageDisplay } from './translatedLabels';
import { useIframeStyles } from './useWebPage.styles';

const getIframeSrc = (url: string) =>
  /^http/.test(url) ? url : `http://${url}`;

const refreshIframe = (iframeRef) => {
  if (iframeRef.current) {
    // biome-ignore lint/correctness/noSelfAssign: <explanation>
    iframeRef.current.src = iframeRef.current?.src;
  }
};

const WebPage = ({
  panelOptions,
  globalRefreshInterval,
  id: widgetId
}: WebPageProps): JSX.Element => {
  const { classes } = useIframeStyles();
  const { t } = useTranslation();

  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [intervalId, setIntervalId] = useState<number | null>(null);

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

    const id = window.setInterval(
      () => refreshIframe(iframeRef),
      refreshIntervalToUse
    );
    setIntervalId(id);

    return () => clearInterval(id);
  }, [refreshIntervalToUse]);

  if (!url) {
    return <Preview />;
  }

  return (
    <div className={classes.container}>
      <iframe
        ref={iframeRef}
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
