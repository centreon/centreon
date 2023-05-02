import { Responsive } from '@visx/visx';
import { omit } from 'ramda';
import ScaleText from 'react-scale-text';

import { TypographyProps, useTheme } from '@mui/material';

export interface AdaptativeTypographyProps
  extends Pick<TypographyProps, 'variant'> {
  text: string;
}

const AdaptativeTypography = ({
  text,
  variant = 'body1'
}: AdaptativeTypographyProps): JSX.Element => {
  const theme = useTheme();

  return (
    <Responsive.ParentSizeModern>
      {(): JSX.Element => {
        return (
          <span
            style={{
              ...omit(['fontSize'], theme.typography[variant]),
              lineHeight: 1.2
            }}
          >
            <ScaleText>{text}</ScaleText>
          </span>
        );
      }}
    </Responsive.ParentSizeModern>
  );
};

export default AdaptativeTypography;
