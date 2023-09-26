/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { CircularProgress, Typography } from '@mui/material';

import { Avatar } from '@centreon/ui/components';
import { SingleAutocompleteField } from '@centreon/ui';

import {
  labelAvailable,
  labelMetrics,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import { isAtLeastOneResourceFullfilled } from '../utils';
import { editProperties } from '../../../../useCanEditDashboard';

import useMetricsOnly from './useMetricsOnly';

const Metric = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
  const { t } = useTranslation();

  const {
    metrics,
    changeMetric,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    resources,
    selectedMetric,
    getOptionLabel
  } = useMetricsOnly(propertyName);

  const { canEditField } = editProperties.useCanEditProperties();

  const canDisplayMetricsSelection =
    isAtLeastOneResourceFullfilled(resources) && !hasTooManyMetrics;

  const title =
    metricCount && isAtLeastOneResourceFullfilled(resources)
      ? `${t(labelMetrics)} (${metricCount} ${labelAvailable})`
      : t(labelMetrics);

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        3
      </Avatar>
      <Typography>{title}</Typography>
      {isLoadingMetrics && <CircularProgress size={16} />}
    </div>
  );

  return (
    <div className={classes.resourcesContainer}>
      {header}
      {canDisplayMetricsSelection && (
        <SingleAutocompleteField
          className={classes.resources}
          disabled={!canEditField || isLoadingMetrics}
          getOptionItemLabel={getOptionLabel}
          getOptionLabel={getOptionLabel}
          isOptionEqualToValue={(option, selectedValue) =>
            equals(option?.id, selectedValue?.id)
          }
          label={t(labelMetrics)}
          options={metrics}
          value={selectedMetric || undefined}
          onChange={changeMetric}
        />
      )}
      {hasTooManyMetrics && (
        <Typography sx={{ color: 'text.disabled' }}>
          {t(labelYouHaveTooManyMetrics)}
        </Typography>
      )}
    </div>
  );
};

export default Metric;
