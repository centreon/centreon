import { baseEndpoint } from '../../../../api/endpoint';

interface DetailExclusionPeriodByAnomalyDetection {
  anomalyDetectionServiceId: number;
  exclusionId: number;
}

export const excludePeriodEndPoint = (
  anomalyDetectionServiceId: number
): string =>
  `${baseEndpoint}/anomaly-detection/service/${anomalyDetectionServiceId}/exclusion-Periods`;

export const exclusionPeriodsByExclusionIdEndPoint = ({
  anomalyDetectionServiceId,
  exclusionId
}: DetailExclusionPeriodByAnomalyDetection): string =>
  `${baseEndpoint}/anomaly-detection/service/${anomalyDetectionServiceId}/exclusion-Periods/${exclusionId}`;
