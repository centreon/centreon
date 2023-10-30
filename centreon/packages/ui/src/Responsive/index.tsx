import { ReactNode, useEffect, useRef, useState } from 'react';

import { Box } from '@mui/material';

interface Props {
  children: ReactNode;
  margin?: number;
}

const Responsive = ({ children, margin = 0 }: Props): JSX.Element => {
  const containerRef = useRef<HTMLDivElement | null>(null);

  const [windowHeight, setWindowHeight] = useState(window.innerHeight);
  const [clientRect, setClientRect] = useState<DOMRect | null>(null);

  const resize = (): void => {
    setWindowHeight(window.innerHeight);
  };

  useEffect(() => {
    window.addEventListener('resize', resize);

    setClientRect(containerRef.current?.getBoundingClientRect() ?? null);

    return () => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  const containerHeight = windowHeight - (clientRect?.top || 0) - margin;

  return (
    <Box
      ref={containerRef}
      sx={{
        height: `${containerHeight}px`,
        overflowY: 'auto'
      }}
    >
      {children}
    </Box>
  );
};

export default Responsive;
