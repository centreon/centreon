export interface Data {
  start: string;
  end: string;
  color: string;
}

export interface Tooltip {
  start: string;
  end: string;
  color: string;
  duration: string;
}

export interface TimelineProps {
  data: Array<Data>;
  startDate: string;
  endDate: string;
  TooltipContent?: (props: Tooltip) => JSX.Element;
  tooltipClassName?: string;
}
