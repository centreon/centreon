import { useEffect, useRef, useState } from 'react';

import { useAtomValue } from 'jotai';

import { LinearProgress } from '@mui/material';

import { displayedDashboardAtom } from '../atoms';

const TimeLeftLoader = ({ rotationTime }) => {
  const [progress, setProgress] = useState(100);
  const timeLeftRef = useRef<NodeJS.Timeout | null>(null);

  const displayedDashboardId = useAtomValue(displayedDashboardAtom);

  useEffect(() => {
    clearInterval(timeLeftRef.current);

    timeLeftRef.current = setInterval(() => {
      setProgress((oldProgress) => {
        return Math.max(oldProgress - 100 / (rotationTime * 10), 0);
      });
    }, 100);
    setProgress(100);

    return () => {
      clearInterval(timeLeftRef.current);
    };
  }, [displayedDashboardId]);

  return (
    <LinearProgress
      sx={{
        borderRadius: 0.5,
        transform: 'rotate3d(0, 0, 1, 180deg)',
        width: '30vw'
      }}
      value={progress}
      variant="determinate"
    />
  );
};

export default TimeLeftLoader;
