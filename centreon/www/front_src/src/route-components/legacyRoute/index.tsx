import { useState, useEffect } from 'react';

import { useLocation, useNavigate } from 'react-router-dom';
import { equals, includes, isNil, replace } from 'ramda';

import { PageSkeleton, useFullscreen } from '@centreon/ui';

const LegacyRoute = (): JSX.Element => {
  const [loading, setLoading] = useState(true);
  const location = useLocation();
  const navigate = useNavigate();

  const { toggleFullscreen } = useFullscreen();

  const handleHref = (event): void => {
    const { href } = event.detail;

    window.history.pushState(null, href, href);
  };

  const load = (): void => {
    setLoading(false);

    document
      .getElementById('main-content')
      ?.contentWindow?.document.querySelectorAll('a')
      .forEach((element) => {
        element.addEventListener(
          'click',
          (e) => {
            const href = (e.target as HTMLLinkElement).getAttribute('href');
            const target = (e.target as HTMLLinkElement).getAttribute('target');

            if (equals(target, '_blank')) {
              return;
            }

            e.preventDefault();

            if (isNil(href)) {
              return;
            }

            const formattedHref = replace('./', '', href);

            if (
              equals(formattedHref, '#') ||
              !formattedHref.match(/^main.php/)
            ) {
              return;
            }

            navigate(`/${formattedHref}`, { replace: true });
          },
          { once: true }
        );
      });
  };

  const toggle = (event: KeyboardEvent): void => {
    if (
      includes(
        document.getElementById('main-content')?.contentWindow?.document
          .activeElement?.tagName,
        ['INPUT', 'TEXTAREA']
      ) ||
      equals(
        document
          .getElementById('main-content')
          ?.contentWindow?.document.activeElement?.getAttribute(
            'contenteditable'
          ),
        'true'
      ) ||
      !equals(event.code, 'KeyF')
    ) {
      return;
    }

    toggleFullscreen(document.querySelector('body'));
  };

  useEffect(() => {
    window.addEventListener('react.href.update', handleHref, false);
    document
      .getElementById('main-content')
      ?.contentWindow?.addEventListener('keypress', toggle, false);

    return () => {
      window.removeEventListener('react.href.update', handleHref);
      document
        .getElementById('main-content')
        ?.contentWindow?.removeEventListener('keypress', toggle);
    };
  }, []);

  const { search, hash } = location;

  const params = (search || '') + (hash || '');

  return (
    <>
      {loading && <PageSkeleton />}
      <iframe
        frameBorder="0"
        id="main-content"
        name="main-content"
        scrolling="yes"
        src={`./main.get.php${params}`}
        style={{ height: '100%', width: '100%' }}
        title="Main Content"
        onLoad={load}
      />
    </>
  );
};

export default LegacyRoute;
