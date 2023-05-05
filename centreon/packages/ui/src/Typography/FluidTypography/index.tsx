import { useRef } from 'react';

import { Typography, TypographyProps } from '@mui/material';

import useFluidResizeObserver from './useFluidResizeObserver';

type CustomTypographyProps = Pick<TypographyProps, 'variant'>;
export interface FluidTypographyProps extends CustomTypographyProps {
  className?: string;
  text: string;
}

const FluidTypography = ({
  text,
  variant = 'body1',
  className
}: FluidTypographyProps): JSX.Element => {
  const containerRef = useRef<HTMLElement>();
  const parentRef = useRef<HTMLElement>();

  const size = useFluidResizeObserver({ ref: containerRef });
  const parentSize = useFluidResizeObserver({ isParent: true, ref: parentRef });

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
