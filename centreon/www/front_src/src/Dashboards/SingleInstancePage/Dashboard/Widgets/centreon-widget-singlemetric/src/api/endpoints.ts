interface Props {
  hostId: number;
  serviceId: number;
  metricName: string;
}

export const getMetricsEndpoint = ({ hostId, serviceId, metricName }: Props) =>
  `./api/latest/monitoring/hosts/${hostId}/services/${serviceId}/metrics/${metricName}`;
