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
  labelThresholdsAreAutomaticallyHidden,
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
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { widgetPropertiesAtom } from '../../../atoms';

import useMetrics from './useMetrics';
import { useMetricsStyles } from './Metrics.styles';

const Metric = ({ propertyName }: WidgetPropertyProps): JSX.Element | null => {
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
    renderOptionsForMultipleMetricsAndResources,
    getMetricOptionDisabled,
    hasMetaService
  } = useMetrics(propertyName);

  const { canEditField } = useCanEditProperties();
  const widgetProperties = useAtomValue(widgetPropertiesAtom);

  const canDisplayMetricsSelection =
    areResourcesFullfilled(resources) && !hasTooManyMetrics;

  const title =
    metricCount && isAtLeastOneResourceFullfilled(resources)
      ? `${t(labelMetrics)} (${metricCount} ${labelAvailable})`
      : t(labelMetrics);

  const warningMessages = [
    error && isTouched && error,
    widgetProperties?.singleMetricSelection && metricWithSeveralResources && (
      <>
        <strong>{metricWithSeveralResources}</strong>{' '}
        {t(labelIsTheSelectedResource)}
      </>
    )
  ];

  if (hasMetaService) {
    return null;
  }

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        3
      </Avatar>
      <Typography className={classes.resourceTitle}>{title}</Typography>
      {isLoadingMetrics && <CircularProgress size={16} />}
    </div>
  );

  return (
    <div className={classes.resourcesContainer}>
      {header}
      <div className={classes.resourceComposition}>
        {widgetProperties?.singleMetricSelection &&
        widgetProperties?.singleResourceSelection ? (
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
            getOptionDisabled={getMetricOptionDisabled}
            getOptionLabel={getOptionLabel}
            getOptionTooltipLabel={getOptionLabel}
            getTagLabel={getTagLabel}
            label={t(labelSelectMetric)}
            options={metrics}
            renderOption={
              widgetProperties?.singleMetricSelection
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
      </div>
      {hasTooManyMetrics && (
        <Typography sx={{ color: 'text.disabled' }}>
          {t(labelYouHaveTooManyMetrics)}
        </Typography>
      )}
      <div>
        {warningMessages.map((content) => (
          <Typography
            className={classes.warningText}
            key={content?.toString()}
            variant="body2"
          >
            {content}
          </Typography>
        ))}
        {hasReachedTheLimitOfUnits && (
          <div>
            <span>{t(labelYouCanSelectUpToTwoMetricUnits)}</span>
            <br />
            <span>{t(labelThresholdsAreAutomaticallyHidden)}</span>
          </div>
        )}
      </div>
    </div>
  );
};

export default Metric;
