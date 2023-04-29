import { Responsive } from '@visx/visx';
import { omit, replace } from 'ramda';
import ScaleText from 'react-scale-text';

import { useTheme } from '@mui/material';

const AdaptativeTypography = ({ text, variant = 'body1' }): JSX.Element => {
  const theme = useTheme();

  const formattedText = replace(/ /g, '\u00A0', text);

  return (
    <Responsive.ParentSizeModern>
      {(): JSX.Element => {
        return (
          <span
            style={{
              ...omit(['fontSize'], theme.typography[variant]),
              lineHeight: 1
            }}
          >
            <ScaleText>{formattedText}</ScaleText>
          </span>
        );
      }}
    </Responsive.ParentSizeModern>
  );
};

export default AdaptativeTypography;
