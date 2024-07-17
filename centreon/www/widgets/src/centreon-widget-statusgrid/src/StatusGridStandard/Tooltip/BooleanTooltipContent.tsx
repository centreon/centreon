import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';
import { Link } from 'react-router-dom';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  useTheme
} from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { ResourceData } from '../models';
import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { getBooleanRuleLink, getColor } from '../utils';
import {
  labelClickHereForDetails,
  labelExpressionIn,
  labelImpactAppliedWhen
} from '../translatedLabels';

import useBooleanTooltipContent from './useBooleanTooltipContent';

interface Props {
  data: ResourceData;
}

const BooleanTooltipContent = ({ data }: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const theme = useTheme();

  const { classes } = useHostTooltipContentStyles();
  const { format } = useLocaleDateTimeFormat();

  const { isLoading, statusName, isImpactingWhenTrue } =
    useBooleanTooltipContent(data);

  return (
    <Box className={classes.container}>
      <Box className={classes.header}>
        <Typography
          data-resourceName={data.name}
          sx={{
            color: getColor({ severityCode: data.status, theme })
          }}
        >
          <strong>{data.name}</strong>
        </Typography>
        <Box>
          <Typography variant="body2">
            {t(labelImpactAppliedWhen)}{' '}
            {t(isImpactingWhenTrue?.toString() as string)}
          </Typography>
        </Box>
      </Box>

      <Box className={classes.body}>
        <Box className={classes.boleanRulebody}>
          {isLoading && <CircularProgress size={24} />}
          <Box component="span">
            <Typography component="span">
              {t(labelExpressionIn)}{' '}
              <Typography component="span">
                {t(statusName?.toLocaleLowerCase() as string)}.
              </Typography>
            </Typography>
            <Typography
              className={classes.boleanRuleLinkWrapper}
              component="span"
            >
              <Link
                aria-label={t(labelClickHereForDetails)}
                className={classes.boleanRuleLink}
                target="_blank"
                to={getBooleanRuleLink(data?.resourceId)}
              >
                {t(labelClickHereForDetails)}
              </Link>
            </Typography>
          </Box>
        </Box>
        <Divider variant="middle" />
        <Typography
          className={classes.dateContainer}
          color="text.secondary"
          variant="body2"
        >
          {format({ date: dayjs().toISOString(), formatString: 'LLL' })}
        </Typography>
      </Box>
    </Box>
  );
};

export default BooleanTooltipContent;
