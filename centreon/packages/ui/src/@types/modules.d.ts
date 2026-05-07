// Ambient declarations for third-party modules without bundled type definitions.
// biome-ignore lint/style/useNamingConvention: external module declaration

declare module 'd3-scale' {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleLinear<Range = any, Output = any, Unknown = never> = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleTime<Range = any, Output = any, Unknown = never> = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleBand<Domain extends { toString(): string } = any> = any;
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  export type ScaleOrdinal<
    Domain extends { toString(): string } = any,
    Range = any,
    Unknown = never
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
