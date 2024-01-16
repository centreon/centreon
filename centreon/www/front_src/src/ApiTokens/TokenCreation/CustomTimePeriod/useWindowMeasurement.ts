import { useState, useEffect } from 'react';

interface Measurement {
  height: number;
  width: number;
}
const useWindowMeasurement = (): Measurement => {
  const [height, setHeight] = useState<number>(window.innerHeight);
  const [width, setWidth] = useState<number>(window.innerWidth);

  const resize = (): void => {
    setHeight(window.innerHeight);
    setWidth(window.innerWidth);
  };
  useEffect(() => {
    window.addEventListener('resize', resize);

    return (): void => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  return { height, width };
};

export default useWindowMeasurement;
