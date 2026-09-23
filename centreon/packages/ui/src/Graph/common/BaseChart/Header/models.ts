export interface LineChartHeader {
  displayTitle?: boolean;
  // @ts-expect-error - suppressing pre-existing type mismatch
  extraComponent?: ReactNode;
}
