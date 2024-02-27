import { isNil, pipe, propOr } from 'ramda';

const getCumulativeOffset = (element?: HTMLElement): number => {
  if (pipe(propOr(undefined, 'offsetParent'), isNil)(element)) {
    return 0;
  }

  const actualElement = element as HTMLElement;

  return (
    getCumulativeOffset(actualElement.offsetParent as HTMLElement) +
    actualElement.offsetTop
  );
};

export default getCumulativeOffset;
