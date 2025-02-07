import { dec, equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, CircularProgress, Typography, useTheme } from '@mui/material';

import { useTooltipStyles } from '../Tooltip.styles';

import {
  NoDisabledHostsLabel,
  NoEnabledHostsLabel,
  labelDisabledHosts,
  labelEnabledHosts
} from '../../../../translatedLabels';
import { useLoadHosts } from './useLoadHosts';

interface Props {
  enabled: boolean;
  hostGroupName: string;
}

const TooltipContent = ({ enabled, hostGroupName }: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { elements, elementRef, isLoading } = useLoadHosts({
    enabled,
    hostGroupName
  });

  const hasNoElements = isEmpty(elements);

  return (
    <Box>
      <Box className={classes.header}>
        <Typography
          fontWeight="bold"
          sx={{
            color: theme.palette.common.white
          }}
        >
          {enabled ? t(labelEnabledHosts) : t(labelDisabledHosts)}
        </Typography>
      </Box>
      <Box className={classes.body}>
        <Box className={classes.listContainer}>
          {hasNoElements ? (
            <Typography color="disabled">
              {enabled ? t(NoEnabledHostsLabel) : t(NoDisabledHostsLabel)}
            </Typography>
          ) : (
            <div>
              {elements.map(({ name }, index) => {
                const isLastElement = equals(dec(elements.length), index);

                return (
                  <Typography
                    data-serviceName={name}
                    key={name}
                    ref={isLastElement ? elementRef : undefined}
                    sx={{
                      color: theme.palette.text.primary,
                      fontSize: theme.typography.body2.fontSize,
                      fontWeight: theme.typography.fontWeightRegular
                    }}
                  >
                    {name}
                  </Typography>
                );
              })}
            </div>
          )}
          {isLoading && <CircularProgress size={24} />}
        </Box>
      </Box>
    </Box>
  );
};

export default TooltipContent;
