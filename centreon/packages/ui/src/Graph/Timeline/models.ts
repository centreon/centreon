export interface TimelineProps {
  data: Array<{
    start: Date,
    end: Date,
    color: string;
  }>;
  startDate: string;
  endDate: string;
  TooltipContent?: ({ start, end, color, duration }) => JSX.Element;
}
