/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { equals, head } from 'ramda';
import { useAtomValue } from 'jotai';

import { CircularProgress, Typography } from '@mui/material';

import { Avatar } from '@centreon/ui/components';
import { MultiAutocompleteField, SingleAutocompleteField } from '@centreon/ui';

import {
  labelAvailable,
  labelIsTheSelectedResource,
  labelMetrics,
  labelSelectMetric,
  labelYouCanSelectUpToTwoMetricUnits,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import {
  areResourcesFullfilled,
  isAtLeastOneResourceFullfilled
} from '../utils';
import {
  singleHostPerMetricAtom,
  singleMetricSelectionAtom
} from '../../../atoms';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useMetrics from './useMetrics';
import { useMetricsStyles } from './Metrics.styles';

const Metric = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
  const { classes: metricsClasses } = useMetricsStyles();
  const { t } = useTranslation();

  const {
    metrics,
    changeMetric,
    hasTooManyMetrics,
    isLoadingMetrics,
    metricCount,
    resources,
    selectedMetrics,
    getOptionLabel,
    getTagLabel,
    deleteMetricItem,
    error,
    isTouched,
    hasReachedTheLimitOfUnits,
    metricWithSeveralResources,
    renderOptionsForSingleMetric,
    renderOptionsForMultipleMetricsAndResources
  } = useMetrics(propertyName);

  const { canEditField } = useCanEditProperties();
  const singleMetricSelection = useAtomValue(singleMetricSelectionAtom);
  const singleHostPerMetric = useAtomValue(singleHostPerMetricAtom);

  const canDisplayMetricsSelection =
    areResourcesFullfilled(resources) && !hasTooManyMetrics;

  const title =
    metricCount && isAtLeastOneResourceFullfilled(resources)
      ? `${t(labelMetrics)} (${metricCount} ${labelAvailable})`
      : t(labelMetrics);

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        3
      </Avatar>
      <div>
        <Typography className={classes.resourceTitle}>{title}</Typography>
        {error && isTouched && (
          <Typography className={classes.warningText} variant="body2">
            {error}
          </Typography>
        )}
        {hasReachedTheLimitOfUnits && (
          <Typography className={classes.warningText} variant="body2">
            {t(labelYouCanSelectUpToTwoMetricUnits)}
          </Typography>
        )}
        {singleMetricSelection && metricWithSeveralResources && (
          <Typography className={classes.warningText} variant="body2">
            <strong>{metricWithSeveralResources}</strong>{' '}
            {t(labelIsTheSelectedResource)}
          </Typography>
        )}
      </div>
      {isLoadingMetrics && <CircularProgress size={16} />}
    </div>
  );

  return (
    <div className={classes.resourcesContainer}>
      {header}
      <div className={classes.resourceComposition}>
        {singleMetricSelection && singleHostPerMetric ? (
          <SingleAutocompleteField
            className={classes.resources}
            disabled={
              !canEditField || isLoadingMetrics || !canDisplayMetricsSelection
            }
            getOptionItemLabel={getOptionLabel}
            getOptionLabel={getOptionLabel}
            isOptionEqualToValue={(option, selectedValue) =>
              equals(option?.id, selectedValue?.id)
            }
            label={t(labelSelectMetric)}
            options={metrics}
            value={head(selectedMetrics || []) || undefined}
            onChange={changeMetric}
          />
        ) : (
          <MultiAutocompleteField
            disableSortedOptions
            ListboxProps={{
              className: metricsClasses.listBox
            }}
            chipProps={{
              color: 'primary',
              onDelete: (_, option): void => deleteMetricItem(option)
            }}
            className={classes.resources}
            disabled={
              !canEditField || isLoadingMetrics || !canDisplayMetricsSelection
            }
            getOptionLabel={getOptionLabel}
            getOptionTooltipLabel={getOptionLabel}
            getTagLabel={getTagLabel}
            label={t(labelSelectMetric)}
            options={metrics}
            renderOption={
              singleMetricSelection
                ? renderOptionsForSingleMetric
                : renderOptionsForMultipleMetricsAndResources
            }
            value={selectedMetrics || []}
            onChange={(event) => {
              event.preventDefault();
              event.stopPropagation();
            }}
          />
        )}
        {hasTooManyMetrics && (
          <Typography sx={{ color: 'text.disabled' }}>
            {t(labelYouHaveTooManyMetrics)}
          </Typography>
        )}
      </div>
    </div>
  );
};

export default Metric;
