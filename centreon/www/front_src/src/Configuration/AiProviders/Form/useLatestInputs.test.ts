import { renderHook } from '@testing-library/react';

import { useLatestInputs } from './useLatestInputs';

describe('useLatestInputs', () => {
  it('drops the answer of an older call once a newer call is made', () => {
    const { result } = renderHook(() => useLatestInputs('inputs'));

    const isFirstCurrent = result.current();

    expect(isFirstCurrent()).toBe(true);

    const isSecondCurrent = result.current();

    expect(isFirstCurrent()).toBe(false);
    expect(isSecondCurrent()).toBe(true);
  });

  it('drops an answer when the inputs changed meanwhile', () => {
    const { result, rerender } = renderHook(
      ({ inputs }) => useLatestInputs(inputs),
      { initialProps: { inputs: 'first' } }
    );

    const isCurrent = result.current();

    rerender({ inputs: 'second' });

    expect(isCurrent()).toBe(false);
  });

  it('keeps an answer when the hook re-renders with the same inputs', () => {
    const { result, rerender } = renderHook(
      ({ inputs }) => useLatestInputs(inputs),
      { initialProps: { inputs: 'same' } }
    );

    const isCurrent = result.current();

    rerender({ inputs: 'same' });

    expect(isCurrent()).toBe(true);
  });

  it('drops an answer arriving after unmount', () => {
    const { result, unmount } = renderHook(() => useLatestInputs('inputs'));

    const isCurrent = result.current();

    unmount();

    expect(isCurrent()).toBe(false);
  });
});
