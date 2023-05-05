import {
  MutableRefObject,
  useCallback,
  useEffect,
  useRef,
  useState
} from 'react';

import { Typography, TypographyProps } from '@mui/material';

type CustomTypographyProps = Pick<TypographyProps, 'variant'>;
export interface FluidTypographyProps extends CustomTypographyProps {
  className?: string;
  text: string;
}

interface Size {
  height: number;
  width: number;
}

const useResizeObserver = (
  ref: MutableRefObject<HTMLElement | undefined>,
  isParent?: boolean
): Size => {
  const [size, setSize] = useState<Size>({ height: 0, width: 0 });

  const observer = useRef<ResizeObserver | null>(null);

  const resizeObserver = useCallback(
    (element) => {
      if (observer.current) {
        observer.current.disconnect();
      }

      observer.current = new ResizeObserver(
        ([entry]: Array<ResizeObserverEntry>) => {
          setSize({
            height: entry.target?.getBoundingClientRect().height || 0,
            width: entry.target?.getBoundingClientRect().width || 0
          });
        }
      );

      if (element && observer.current) {
        observer.current.observe(element);
      }
    },
    [ref.current]
  );

  useEffect(() => {
    resizeObserver(isParent ? ref.current?.parentElement : ref.current);
  }, [ref.current]);

  return size;
};

const FluidTypography = ({
  text,
  variant = 'body1',
  className
}: FluidTypographyProps): JSX.Element => {
  const containerRef = useRef<HTMLElement>();
  const parentRef = useRef<HTMLElement>();

  const size = useResizeObserver(containerRef);
  const parentSize = useResizeObserver(parentRef, true);

  return (
    <div
      ref={parentRef}
      style={{
        height: `${parentSize.height}px`,
        width: `100%`
      }}
    >
      <div ref={containerRef} style={{ height: '100%', width: '100%' }}>
        <Typography
          className={className}
          sx={{
            fontSize: `clamp(10px, min(${Math.floor(
              size.width / 6
            )}px, ${Math.floor(size.height / 6)}px), min(${size.width}px, ${
              size.height
            }px))`
          }}
          variant={variant}
        >
          {text}
        </Typography>
      </div>
    </div>
  );
};

export default FluidTypography;
