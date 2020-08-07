import { isNil, pipe, prop } from 'ramda';

const getCumulativeOffset = (element?: HTMLElement): number => {
  if (pipe(prop('offsetParent'), isNil)(element)) {
    return 0;
  }

  const actualElement = element as HTMLElement;

  return (
    getCumulativeOffset(actualElement.offsetParent as HTMLElement) +
    actualElement.offsetTop
  );
};

export default getCumulativeOffset;
