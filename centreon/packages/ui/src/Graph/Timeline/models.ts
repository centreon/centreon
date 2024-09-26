export interface TimelineProps {
  data: Array<object>;
  start_date: string;
  end_date: string;
  TooltipContent?: ({ start, end, color, duration }) => JSX.Element;
}
