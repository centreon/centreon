import { act, renderHook } from '@testing-library/react';
import { atom, createStore, Provider } from 'jotai';
import type { ReactNode } from 'react';

import { useLatestRequest } from './useLatestRequest';

const getWrapper =
  (store: ReturnType<typeof createStore>) =>
  ({ children }: { children: ReactNode }): JSX.Element => (
    <Provider store={store}>{children}</Provider>
  );

describe('useLatestRequest', () => {
  it('only holds the latest request as current, with a ref', () => {
    const { result } = renderHook(() => useLatestRequest(), {
      wrapper: getWrapper(createStore())
    });

    const isFirstLatest = result.current();

    expect(isFirstLatest()).toBe(true);

    const isSecondLatest = result.current();

    expect(isFirstLatest()).toBe(false);
    expect(isSecondLatest()).toBe(true);
  });

  it('does not carry a ref counter across a remount', () => {
    const store = createStore();
    const first = renderHook(() => useLatestRequest(), {
      wrapper: getWrapper(store)
    });
    const isFirstLatest = first.result.current();

    first.unmount();

    const second = renderHook(() => useLatestRequest(), {
      wrapper: getWrapper(store)
    });
    second.result.current();

    // each instance numbers its own requests
    expect(isFirstLatest()).toBe(true);
  });

  it('supersedes a request across a remount with an atom', () => {
    const store = createStore();
    const counterAtom = atom(0);
    const first = renderHook(() => useLatestRequest(counterAtom), {
      wrapper: getWrapper(store)
    });
    const isFirstLatest = first.result.current();

    first.unmount();

    const second = renderHook(() => useLatestRequest(counterAtom), {
      wrapper: getWrapper(store)
    });
    let isSecondLatest = (): boolean => false;

    act(() => {
      isSecondLatest = second.result.current();
    });

    expect(isFirstLatest()).toBe(false);
    expect(isSecondLatest()).toBe(true);
    expect(store.get(counterAtom)).toBe(2);
  });
});
