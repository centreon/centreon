import { Responsive } from '@visx/visx';
import { omit } from 'ramda';
import ScaleText from 'react-scale-text';
import { makeStyles } from 'tss-react/mui';

import { TypographyProps } from '@mui/material';

type CustomTypographyProps = Required<Pick<TypographyProps, 'variant'>>;
export interface AdaptativeTypographyProps extends CustomTypographyProps {
  className: string;
  text: string;
}

const useStyles = makeStyles<CustomTypographyProps>()((theme, { variant }) => ({
  container: {
    ...omit(['fontSize'], theme.typography[variant])
  }
}));

const AdaptativeTypography = ({
  text,
  variant = 'body1',
  className
}: AdaptativeTypographyProps): JSX.Element => {
  const { classes, cx } = useStyles({ variant });

  return (
    <Responsive.ParentSizeModern>
      {(): JSX.Element => {
        return (
          <span className={cx(classes.container, className)}>
            <ScaleText>{text}</ScaleText>
          </span>
        );
      }}
    </Responsive.ParentSizeModern>
  );
};

export default AdaptativeTypography;
