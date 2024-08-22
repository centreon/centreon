import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography, useTheme } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { labelBaseColor } from '../../../../translatedLabels';

import { useBaseColorStyles } from './BaseColor.styles';

interface Props {
  changeBaseColor: (color: string) => () => void;
  customBaseColor?: string;
}

const BaseColor = ({
  customBaseColor,
  changeBaseColor
}: Props): JSX.Element => {
  const { classes } = useBaseColorStyles();

  const { t } = useTranslation();

  const theme = useTheme();

  const colorOptions = [
    theme.palette.primary.main,
    theme.palette.secondary.main,
    theme.palette.pending.main,
    theme.palette.action.disabled
  ];

  return (
    <div className={classes.container}>
      <Typography>{t(labelBaseColor)}</Typography>
      <div className={classes.options}>
        {colorOptions.map((color) => (
          <IconButton key={color} onClick={changeBaseColor(color)}>
            <Box
              className={classes.option}
              sx={{
                borderColor: equals(color, customBaseColor)
                  ? color
                  : 'transparent'
              }}
            >
              <Box
                className={classes.optionContent}
                sx={{ backgroundColor: color }}
              />
            </Box>
          </IconButton>
        ))}
      </div>
    </div>
  );
};

export default BaseColor;
