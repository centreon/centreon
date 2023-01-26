import { baseEndpoint } from '../../../../api/endpoint';

interface ExclusionPeriodByAnomalyDetection {
  anomalyDetectionServiceId: number;
  exclusionId: number;
}

export const getExcludePeriodEndPoint = ({
  anomalyDetectionServiceId
}: Pick<
  ExclusionPeriodByAnomalyDetection,
  'anomalyDetectionServiceId'
>): string =>
  `${baseEndpoint}/anomaly-detection/service/${anomalyDetectionServiceId}/exclusion-Periods`;

export const getExclusionPeriodsByExclusionIdEndPoint = ({
  anomalyDetectionServiceId,
  exclusionId
}: ExclusionPeriodByAnomalyDetection): string =>
  `${baseEndpoint}/anomaly-detection/service/${anomalyDetectionServiceId}/exclusion-Periods/${exclusionId}`;
