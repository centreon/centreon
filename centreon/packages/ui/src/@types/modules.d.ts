// Ambient declarations for third-party modules without bundled type definitions.

declare module 'd3-scale' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleLinear<_Range = any, _Output = any, _Unknown = never> = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleTime<_Range = any, _Output = any, _Unknown = never> = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleBand<_Domain extends { toString(): string } = any> = any;
  export type ScaleOrdinal<
    // biome-ignore lint/suspicious/noExplicitAny: typing fallback
    _Domain extends { toString(): string } = any,
    // biome-ignore lint/suspicious/noExplicitAny: typing fallback
    _Range = any,
    _Unknown = never
    // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  > = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export const scaleLinear: any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export const scaleTime: any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export const scaleBand: any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export const scaleOrdinal: any;
}

declare module 'numeral' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const numeral: any;
  export default numeral;
}

declare module 'pluralize' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const pluralize: any;
  export default pluralize;
}

declare module 'sanitize-html' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type IOptions = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const sanitize: any;
  export default sanitize;
}

declare module 'humanize-duration' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const humanizeDuration: any;
  export default humanizeDuration;
}

declare module 'react-dom/test-utils' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export const act: any;
}

declare module 'react-transition-group/Transition' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type TransitionProps = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type TransitionActions = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  const Transition: any;
  export default Transition;
}
