import { useTranslation } from 'react-i18next';

import { Box, Typography, useTheme } from '@mui/material';

import {
  labelDisabledHosts,
  labelEnabledHosts,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from '../../translatedLabels';

import { Pagination, centreonBaseURL } from '@centreon/ui';
import { useTooltipStyles } from './HostsCount.styles';

interface Props {
  enabled: boolean;
  hostGroupName: string;
}

const goToUrl = ({ id }): void => {
  const url = `/main.php?p=60101&o=c&host_id=${id}`;

  window?.open(`${centreonBaseURL}${url}`, '_blank,noopener,noreferrer');
};

const TooltipContent = ({ enabled, hostGroupName }: Props): JSX.Element => {
  const { classes } = useTooltipStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const labelHasNoElements = enabled
    ? labelNoEnabledHosts
    : labelNoDisabledHosts;

  const searchConditions = [
    {
      field: 'group.name',
      values: {
        $in: [hostGroupName]
      }
    },
    {
      field: 'is_activated',
      values: {
        $eq: enabled
      }
    }
  ];

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
        <Pagination
          api={{
            baseEndpoint: '/configuration/hosts',
            queryKey: ['hosts', hostGroupName, enabled.toString()],
            searchConditions
          }}
          labelHasNoElements={labelHasNoElements}
          onItemClick={goToUrl}
        />
      </Box>
    </Box>
  );
};

export default TooltipContent;
