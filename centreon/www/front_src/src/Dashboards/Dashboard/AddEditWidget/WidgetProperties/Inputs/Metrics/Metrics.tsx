/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { equals, head, isEmpty, isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import { CircularProgress, FormHelperText, Typography } from '@mui/material';

import { Avatar, ItemComposition } from '@centreon/ui/components';
import {
  MultiAutocompleteField,
  SelectField,
  SingleAutocompleteField
} from '@centreon/ui';

import {
  labelAddMetric,
  labelAvailable,
  labelDelete,
  labelMetrics,
  labelServiceName,
  labelYouCanSelectUpToTwoMetricUnits,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';
import { singleMetricSectionAtom } from '../../../atoms';
import { isAtLeastOneResourceFullfilled } from '../utils';
import { useCanEditProperties } from '../../../../useCanEditDashboard';

import useMetrics from './useMetrics';

const Metrics = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
  const { t } = useTranslation();

  const singleMetricSection = useAtomValue(singleMetricSectionAtom);

  const {
    hasNoResources,
    addMetric,
    hasTooManyMetrics,
    deleteMetric,
    value,
    serviceOptions,
    changeService,
    getMetricsFromService,
    changeMetrics,
    changeMetric,
    metricCount,
    isLoadingMetrics,
    error,
    getMetricOptionDisabled,
    getOptionLabel,
    hasReachedTheLimitOfUnits,
    addButtonHidden,
    resources
  } = useMetrics(propertyName);

  const { canEditField } = useCanEditProperties();

  const addButtonDisabled =
    hasNoResources() || hasTooManyMetrics || !metricCount;

  const canDisplayMetricsSelection =
    isAtLeastOneResourceFullfilled(resources) && !hasTooManyMetrics;

  const title = metricCount
    ? `${t(labelMetrics)} (${metricCount} ${labelAvailable})`
    : t(labelMetrics);

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        3
      </Avatar>
      <Typography>{title}</Typography>
      {hasReachedTheLimitOfUnits && (
        <Typography
          component="span"
          sx={{ color: 'warning.main' }}
          variant="body2"
        >
          {' '}
          {t(labelYouCanSelectUpToTwoMetricUnits)}
        </Typography>
      )}
      {isLoadingMetrics && <CircularProgress size={16} />}
    </div>
  );

  return (
    <div className={classes.resourcesContainer}>
      {header}
      {canDisplayMetricsSelection && (
        <ItemComposition
          addButtonHidden={!canEditField || addButtonHidden}
          addbuttonDisabled={addButtonDisabled}
          labelAdd={t(labelAddMetric)}
          onAddItem={addMetric}
        >
          {value.map((service, index) => (
            <ItemComposition.Item
              className={classes.resourceCompositionItem}
              deleteButtonHidden={!canEditField || addButtonHidden}
              key={`${index}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteMetric(index)}
            >
              <SelectField
                ariaLabel={t(labelServiceName) as string}
                className={classes.resourceType}
                dataTestId={labelServiceName}
                disabled={!canEditField || isLoadingMetrics}
                label={t(labelServiceName) as string}
                options={serviceOptions}
                selectedOptionId={service.id}
                onChange={changeService(index)}
              />
              {singleMetricSection ? (
                <SingleAutocompleteField
                  className={classes.resources}
                  disabled={
                    !canEditField ||
                    isNil(service.id) ||
                    isEmpty(service.id) ||
                    isLoadingMetrics
                  }
                  getOptionItemLabel={getOptionLabel}
                  getOptionLabel={getOptionLabel}
                  isOptionEqualToValue={(option, selectedValue) =>
                    equals(option?.id, selectedValue?.id)
                  }
                  label={t(labelMetrics)}
                  options={getMetricsFromService(service.id)}
                  value={head(service.metrics) || undefined}
                  onChange={changeMetric(index)}
                />
              ) : (
                <MultiAutocompleteField
                  chipProps={{
                    color: 'primary'
                  }}
                  className={classes.resources}
                  disabled={
                    !canEditField ||
                    isNil(service.id) ||
                    isEmpty(service.id) ||
                    isLoadingMetrics
                  }
                  getOptionDisabled={getMetricOptionDisabled}
                  getOptionLabel={getOptionLabel}
                  getTagLabel={getOptionLabel}
                  label={t(labelMetrics)}
                  limitTags={1}
                  options={getMetricsFromService(service.id)}
                  value={service.metrics || []}
                  onChange={changeMetrics(index)}
                />
              )}
            </ItemComposition.Item>
          ))}
        </ItemComposition>
      )}
      {hasTooManyMetrics && (
        <Typography sx={{ color: 'text.disabled' }}>
          {t(labelYouHaveTooManyMetrics)}
        </Typography>
      )}
      {error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default Metrics;
