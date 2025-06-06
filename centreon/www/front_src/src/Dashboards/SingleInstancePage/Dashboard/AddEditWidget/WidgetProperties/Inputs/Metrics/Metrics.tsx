import { useAtomValue } from 'jotai';
import { equals, head } from 'ramda';
/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';

import { CircularProgress, Typography } from '@mui/material';

import { MultiAutocompleteField, SingleAutocompleteField } from '@centreon/ui';
import { Avatar } from '@centreon/ui/components';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import {
  labelAvailable,
  labelIsTheSelectedResource,
  labelMetrics,
  labelSelectMetric,
  labelThresholdsAreAutomaticallyHidden,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { widgetPropertiesAtom } from '../../../atoms';
import { WidgetPropertyProps } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';
import {
  areResourcesFullfilled,
  isAtLeastOneResourceFullfilled
} from '../utils';

import { useMetricsStyles } from './Metrics.styles';
import useMetrics from './useMetrics';

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
    hasMultipleUnitsSelected,
    metricWithSeveralResources,
    renderOptionsForSingleMetric,
    renderOptionsForMultipleMetricsAndResources
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
  ].filter((item) => item);

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
            forceInputRenderValue
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
            value={head(selectedMetrics || []) || null}
            onChange={changeMetric}
          />
        ) : (
          <MultiAutocompleteField
            disableSortedOptions
            autocompleteSlotsAndSlotProps={{
              slotProps: {
                listbox: {
                  className: metricsClasses.listBox
                }
              }
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
        {hasMultipleUnitsSelected && (
          <Typography>{t(labelThresholdsAreAutomaticallyHidden)}</Typography>
        )}
      </div>
    </div>
  );
};

export default Metric;
