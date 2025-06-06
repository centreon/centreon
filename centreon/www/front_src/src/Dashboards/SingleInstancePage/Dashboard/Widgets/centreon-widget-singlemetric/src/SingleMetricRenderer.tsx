import { T, always, cond, equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { Gauge, GraphText, SingleBar, Thresholds } from '@centreon/ui';

import { labelCritical, labelWarning } from '../../translatedLabels';

import { useGraphStyles } from './Graph.styles';
import { SingleMetricGraphType } from './models';

interface Props {
  graphProps: {
    baseColor?: string;
    data?;
    displayAsRaw?: boolean;
    thresholds: Thresholds;
  };
  singleMetricGraphType: SingleMetricGraphType;
}

const SingleMetricRenderer = ({
  singleMetricGraphType,
  graphProps
}: Props): JSX.Element => {
  const { classes: graphClasses } = useGraphStyles();

  const { t } = useTranslation();

  const hasTwoThresholds =
    graphProps.thresholds.critical.length === 2 ||
    graphProps.thresholds.warning.length === 2;

  return (
    <Box className={graphClasses.graphContainer}>
      <Box className={graphClasses.content}>
        {cond([
          [equals('gauge'), always(<Gauge {...graphProps} />)],
          [equals('bar'), always(<SingleBar {...graphProps} />)],
          [
            T,
            always(
              <GraphText
                {...graphProps}
                prefThresholds={hasTwoThresholds ? 7 : 11}
                minThresholds="8px"
                labels={{
                  critical: t(labelCritical),
                  warning: t(labelWarning)
                }}
              />
            )
          ]
        ])(singleMetricGraphType)}
      </Box>
    </Box>
  );
};

export default SingleMetricRenderer;
