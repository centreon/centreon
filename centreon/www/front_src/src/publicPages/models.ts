export interface Parameters {
  [x: string]: unknown;
  [x: number]: unknown;
  [x: symbol]: unknown;
}

export interface ComponentProps {
  routeParameters: Parameters;
}
