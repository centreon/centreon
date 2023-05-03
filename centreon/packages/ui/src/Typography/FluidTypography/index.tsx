import { Responsive } from '@visx/visx';
import { omit, replace } from 'ramda';
import ScaleText from 'react-scale-text';
import { makeStyles } from 'tss-react/mui';

import { TypographyProps } from '@mui/material';

type CustomTypographyProps = Pick<TypographyProps, 'variant'>;
export interface FluidTypographyProps extends CustomTypographyProps {
  className?: string;
  text: string;
}

const useStyles = makeStyles<CustomTypographyProps>()((theme, { variant }) => ({
  container: {
    ...omit(['fontSize'], theme.typography[variant || 'body1'])
  }
}));

const FluidTypography = ({
  text,
  variant = 'body1',
  className
}: FluidTypographyProps): JSX.Element => {
  const { classes, cx } = useStyles({ variant });

  const formattedText = replace(/ /g, '\u00A0', text);

  return (
    <Responsive.ParentSize>
      {(): JSX.Element => {
        return (
          <span className={cx(classes.container, className)}>
            <ScaleText>{formattedText}</ScaleText>
          </span>
        );
      }}
    </Responsive.ParentSize>
  );
};

export default FluidTypography;
