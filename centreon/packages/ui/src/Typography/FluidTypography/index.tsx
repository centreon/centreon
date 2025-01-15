import { Typography, TypographyProps } from '@mui/material';

type CustomTypographyProps = Pick<TypographyProps, 'variant' | 'sx'>;
export interface FluidTypographyProps extends CustomTypographyProps {
  className?: string;
  containerClassName?: string;
  max?: string;
  min?: string;
  pref?: number;
  text: string;
}

const FluidTypography = ({
  text,
  variant = 'body1',
  className,
  containerClassName,
  min = '10px',
  max = '1000px',
  pref = 19,
  sx
}: FluidTypographyProps): JSX.Element => {
  return (
    <div
      className={containerClassName}
      style={{
        containerType: 'inline-size',
        height: '100%',
        width: '100%'
      }}
    >
      <Typography
        className={className}
        sx={{
          fontSize: `clamp(${min}, ${pref}cqi, ${max})`,
          ...sx
        }}
        variant={variant}
      >
        {text}
      </Typography>
    </div>
  );
};

export default FluidTypography;
