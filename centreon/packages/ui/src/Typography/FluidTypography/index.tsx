import { useRef } from 'react';

import { Typography, TypographyProps } from '@mui/material';

import useFluidResizeObserver from './useFluidResizeObserver';

type CustomTypographyProps = Pick<TypographyProps, 'variant'>;
export interface FluidTypographyProps extends CustomTypographyProps {
  className?: string;
  containerClassName?: string;
  text: string;
}

const FluidTypography = ({
  text,
  variant = 'body1',
  className,
  containerClassName
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
      <div
        className={containerClassName}
        ref={containerRef}
        style={{ height: '100%', width: '100%' }}
      >
        <Typography
          className={className}
          sx={{
            fontSize: `clamp(10px, max(${Math.floor(
              size.width / 6
            )}px, ${Math.floor(size.height / 6)}px), max(${size.width}px, ${
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
