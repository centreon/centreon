import { dec, equals, isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, CircularProgress, Typography, useTheme } from '@mui/material';

import {
  labelDisabledHosts,
  labelEnabledHosts,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from '../../../translatedLabels';

import { truncate } from '@centreon/ui';
import { useMemo } from 'react';
import { NamedEntity } from '../../../models';
import { useTooltipStyles } from './TooltipContent.styles';
import { useLoadHosts } from './useLoadHosts';

interface Props {
  enabled: boolean;
  hostGroupName: string;
}

interface TooltipBodyProps {
  isLoading: boolean;
  elements: Array<NamedEntity>;
  elementRef;
  enabled: boolean;
}

const TooltipBody = ({
  isLoading,
  elements,
  elementRef,
  enabled
}: TooltipBodyProps): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const hasNoElements = useMemo(
    () => isEmpty(elements) || isNil(elements),
    [elements]
  );

  if (isLoading) {
    return <CircularProgress size={24} />;
  }

  if (hasNoElements) {
    return (
      <Typography color="disabled">
        {enabled ? t(labelNoEnabledHosts) : t(labelNoDisabledHosts)}
      </Typography>
    );
  }
  return (
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
            {truncate({ content: name, maxLength: 30 })}
          </Typography>
        );
      })}
    </div>
  );
};

const TooltipContent = ({ enabled, hostGroupName }: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { elements, elementRef, isLoading } = useLoadHosts({
    enabled,
    hostGroupName
  });

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
          <TooltipBody
            elementRef={elementRef}
            elements={elements}
            enabled={enabled}
            isLoading={isLoading}
          />
        </Box>
      </Box>
    </Box>
  );
};

export default TooltipContent;
