import { ScaleLinear, ScaleTime } from 'd3-scale';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { detailsAtom } from '../../../Details/detailsAtoms';
import { ResourceDetails } from '../../../Details/models';
import { Resource, ResourceType } from '../../../models';
import {
  GetDisplayAdditionalLinesConditionProps,
  Line,
  TimeValue
} from '../models';

import AnomalyDetectionEnvelopeThreshold from './AnomalyDetectionEnvelopeThreshold';
import { displayAdditionalLines } from './helpers';
import { CustomFactorsData } from './models';

interface LinesProps {
  displayAdditionalLines: boolean;
  getTime: (timeValue: TimeValue) => number;
  graphHeight: number;
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
  resource?: Resource | ResourceDetails;
}
const AdditionalLines = ({
  additionalLinesProps,
  data,
  resource
}: AdditionalLinesProps): JSX.Element | null => {
  const details = useAtomValue(detailsAtom);

  const { lines } = additionalLinesProps;

  const isDisplayedThresholds = displayAdditionalLines({
    lines,
    resource: resource ?? (details as ResourceDetails)
  });
  console.log({ data, isDisplayedThresholds });

  if (!isDisplayedThresholds) {
    return null;
  }

  return (
    <div>
      {isDisplayedThresholds && (
        <>
          <AnomalyDetectionEnvelopeThreshold {...additionalLinesProps} />
          <AnomalyDetectionEnvelopeThreshold
            {...additionalLinesProps}
            data={data}
          />
        </>
      )}
    </div>
  );
};

export const getDisplayAdditionalLinesCondition = {
  condition: (resource: Resource | ResourceDetails): boolean => {
    console.log({ resource });

    return equals(resource.type, ResourceType.anomalyDetection);
  },
  displayAdditionalLines: ({
    additionalData,
    additionalLinesProps,
    resource
  }): JSX.Element => (
    <AdditionalLines
      additionalLinesProps={additionalLinesProps}
      data={additionalData}
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
