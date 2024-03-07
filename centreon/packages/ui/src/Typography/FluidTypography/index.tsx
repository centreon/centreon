import { Typography, TypographyProps } from '@mui/material';

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
  return (
    <div
      className={containerClassName}
      style={{
        containerType: 'inline-size',
        height: `100%`,
        width: `100%`
      }}
    >
      <Typography
        className={className}
        sx={{
          fontSize: `clamp(10px, 19cqi, 1000px)`
        }}
        variant={variant}
      >
        {text}
      </Typography>
    </div>
  );
};

export default FluidTypography;
