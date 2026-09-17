interface Props {
  hostId: number;
  serviceId: number;
  metricName: string;
}

export const getMetricsEndpoint = ({ hostId, serviceId, metricName }: Props) =>
  `./api/latest/monitoring/hosts/${hostId}/services/${serviceId}/metrics/${encodeURIComponent(metricName)}`;

interface MetaServiceProps {
  metaServiceId: number;
  metricName: string;
}

export const getMetricsMetaServiceEndpoint = ({
  metaServiceId,
  metricName
}: MetaServiceProps) =>
  `./api/latest/monitoring/metaservice/${metaServiceId}/metrics/${encodeURIComponent(metricName)}`;

interface SelectEndpointProps {
  isMetaServiceSelected: boolean;
  idForService: number;
  hostId: number;
  metricName: string;
}

export const selectEndpoint = ({
  isMetaServiceSelected,
  idForService,
  hostId,
  metricName
}: SelectEndpointProps) => {
  return isMetaServiceSelected
    ? getMetricsMetaServiceEndpoint({ metaServiceId: idForService, metricName })
    : getMetricsEndpoint({ hostId, metricName, serviceId: idForService });
};
