import { useState, useRef, useEffect, RefObject, ChangeEvent } from 'react';

import { useTheme } from '@mui/material';

interface UseAutoSizeProps {
  autoSize: boolean;
  autoSizeCustomPadding?: number;
  autoSizeDefaultWidth: number;
  value?: string;
}

interface UseAutoSizeState {
  changeInputValue: (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ) => void;
  innerValue: string;
  inputRef: RefObject<HTMLDivElement>;
  width: string;
}

const defaultPaddingTotal = 4;

const useAutoSize = ({
  autoSize,
  autoSizeDefaultWidth,
  value,
  autoSizeCustomPadding
}: UseAutoSizeProps): UseAutoSizeState => {
  const [innerValue, setInnerValue] = useState(value || '');
  const [width, setWidth] = useState(autoSizeDefaultWidth);
  const inputRef = useRef();
  const theme = useTheme();

  const changeInputValue = (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ): void => {
    setInnerValue(event.target.value);
  };

  useEffect(() => {
    if (!autoSize) {
      return;
    }

    const newWidth = inputRef.current?.getBoundingClientRect().width || 0;

    setWidth(newWidth < autoSizeDefaultWidth ? autoSizeDefaultWidth : newWidth);
  }, [autoSize && (value || innerValue)]);

  return {
    changeInputValue,
    innerValue,
    inputRef,
    width: `calc(${width}px + ${theme.spacing(
      autoSizeCustomPadding || defaultPaddingTotal
    )})`
  };
};

export default useAutoSize;
