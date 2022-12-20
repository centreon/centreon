import { ScaleLinear, ScaleTime } from 'd3-scale';
import { useAtomValue } from 'jotai/utils';
import { equals } from 'ramda';

import { detailsAtom } from '../../../../Details/detailsAtoms';
import { ResourceDetails } from '../../../../Details/models';
import { Resource, ResourceType } from '../../../../models';
import {
  GetDisplayAdditionalLinesConditionProps,
  Line,
  TimeValue
} from '../../models';
import { CustomFactorsData } from '../models';

import AnomalyDetectionEnvelopeThreshold from './AnomalyDetectionEnvelopeThreshold';
import { displayAdditionalLines } from './helpers';

interface LinesProps {
  displayAdditionalLines: boolean;
  getTime: (timeValue: TimeValue) => number;
  graphHeight: number;
  graphWidth: number;
  leftScale: ScaleLinear<number, number, never>;
  lines: Array<Line>;
  regularLines: Array<Line>;
  rightScale: ScaleLinear<number, number, never>;
  secondUnit: string;
  thirdUnit: string;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number, never>;
}

interface AdditionalLinesProps {
  additionalLinesProps: LinesProps;
  data: CustomFactorsData | null | undefined;
  displayThresholdExclusionPeriod?: boolean;
  resource?: Resource | ResourceDetails;
}
const AdditionalLines = ({
  additionalLinesProps,
  data,
  resource
}: AdditionalLinesProps): JSX.Element | null => {
  const details = useAtomValue(detailsAtom);

  const { lines } = additionalLinesProps;

  const displayThresholds = displayAdditionalLines({
    lines,
    resource: resource ?? (details as ResourceDetails)
  });

  if (!displayThresholds) {
    return null;
  }

  return (
    <>
      <AnomalyDetectionEnvelopeThreshold {...additionalLinesProps} />
      <AnomalyDetectionEnvelopeThreshold
        {...additionalLinesProps}
        data={data}
      />
    </>
  );
};

export const getDisplayAdditionalLinesCondition = {
  condition: (resource: Resource | ResourceDetails): boolean =>
    equals(resource?.type, ResourceType.anomalyDetection),
  displayAdditionalLines: ({
    additionalData,
    additionalLinesProps,
    resource
  }): JSX.Element => (
    <AdditionalLines
      additionalLinesProps={additionalLinesProps}
      data={additionalData}
      displayThresholdExclusionPeriod={false}
      resource={resource}
    />
  )
};

export const getDisplayAdditionalLinesConditionForGraphActions = (
  factorsData?: CustomFactorsData | null
): GetDisplayAdditionalLinesConditionProps => ({
  condition: (resource: Resource | ResourceDetails): boolean =>
    equals(resource.type, ResourceType.anomalyDetection),
  displayAdditionalLines: ({ additionalLinesProps }): JSX.Element => (
    <AdditionalLines
      additionalLinesProps={additionalLinesProps}
      data={factorsData}
    />
  )
});
