export interface Data {
  start: string;
  end: string;
  color: string;
}

export interface TimelineProps {
  data: Array<Data>;
  startDate: string;
  endDate: string;
  TooltipContent?: ({ start, end, color, duration }) => JSX.Element;
}
