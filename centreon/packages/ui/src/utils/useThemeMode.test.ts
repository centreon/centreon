import { renderHook } from '@testing-library/react';
import { act } from 'react-dom/test-utils';

import { useThemeMode } from './useThemeMode';

describe('useThemeMode', () => {
  it('should return object with attribute "isDarkMode" equals to false', () => {
    const { result } = renderHook(() => useThemeMode());

    act(() => {
      result.current.isDarkMode = false;
    });

    expect(result.current.isDarkMode).toBeFalsy();
  });

  it('should return object with attribute "isDarkMode" equals to true', () => {
    const { result } = renderHook(() => useThemeMode());

    act(() => {
      result.current.isDarkMode = true;
    });

    expect(result.current.isDarkMode).toBeTruthy();
  });
});
