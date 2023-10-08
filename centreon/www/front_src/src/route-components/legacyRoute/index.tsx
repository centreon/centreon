import { useState, useEffect } from 'react';

import { useLocation, useNavigate } from 'react-router-dom';
import { equals, isNil, replace } from 'ramda';
import { useTransition, animated } from '@react-spring/web';

import { useTheme } from '@mui/material';

import { PageSkeleton } from '@centreon/ui';

const LegacyRoute = (): JSX.Element => {
  const [loading, setLoading] = useState(true);
  const location = useLocation();
  const navigate = useNavigate();
  const theme = useTheme();

  const transitions = useTransition(location, {
    config: {
      duration: theme.transitions.duration.shortest
    },
    enter: {
      height: '100%',
      opacity: '1',
      width: '100%'
    },
    from: {
      height: '100%',
      opacity: '0',
      width: '100%'
    },
    leave: {
      height: '100%',
      opacity: '0',
      width: '100%'
    }
  });

  const handleHref = (event): void => {
    const { href } = event.detail;

    window.history.pushState(null, href, href);
  };

  const load = (): void => {
    setLoading(false);

    window.frames[0].document.querySelectorAll('a').forEach((element) => {
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

          if (equals(formattedHref, '#') || !formattedHref.match(/^main.php/)) {
            return;
          }

          navigate(`/${formattedHref}`, { replace: true });
        },
        { once: true }
      );
    });
  };

  useEffect(() => {
    window.addEventListener('react.href.update', handleHref, false);

    return () => {
      window.removeEventListener('react.href.update', handleHref);
    };
  }, []);

  const { search, hash } = location;

  const params = (search || '') + (hash || '');

  return transitions((style) => (
    <animated.div style={style}>
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
    </animated.div>
  ));
};

export default LegacyRoute;
