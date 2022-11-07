<<<<<<< HEAD
import { Dispatch, SetStateAction, useEffect, useRef, useState } from 'react';

interface GraphIntersectionState {
  isInViewport: boolean;
  setElement: Dispatch<SetStateAction<HTMLElement | null>>;
}

export const useIntersection = (): GraphIntersectionState => {
  const [entry, setEntry] = useState<IntersectionObserverEntry | null>(null);
  const [element, setElement] = useState<HTMLElement | null>(null);

  const observer = useRef<IntersectionObserver | null>(null);

  useEffect(() => {
=======
import * as React from 'react';

interface GraphIntersectionState {
  isInViewport: boolean;
  setElement: React.Dispatch<React.SetStateAction<HTMLElement | null>>;
}

export const useIntersection = (): GraphIntersectionState => {
  const [entry, setEntry] = React.useState<IntersectionObserverEntry | null>(
    null,
  );
  const [element, setElement] = React.useState<HTMLElement | null>(null);

  const observer = React.useRef<IntersectionObserver | null>(null);

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (observer.current) {
      observer.current.disconnect();
    }

    observer.current = new window.IntersectionObserver(
      ([newEntry]) => setEntry(newEntry),
      {
        threshold: 0,
      },
    );

    if (element) {
      observer.current.observe(element);
    }

    return (): void => {
      observer.current?.disconnect();
    };
  }, [element]);

  return {
    isInViewport: entry?.isIntersecting ?? true,
    setElement,
  };
};
