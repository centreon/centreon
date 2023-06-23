import { Props } from '.';

export interface Status {
  name: string;
  severity_code: number;
}

export interface WithName {
  name: string;
}
export interface TimelineEvent {
  contact?: WithName;
  content: string;
  date: string;
  endDate?: string;
  id: number;
  startDate?: string;
  status?: Status;
  tries?: number;
  type: string;
}

export interface Args extends Omit<Props, 'graphWidth' | 'graphSvgRef'> {
  annotationHoveredId: number;
}
